<?php

namespace App\Core;

/**
 * Indian Standard Time (Asia/Kolkata): mail copy, UI dates/times, automation “today”, DB session.
 * PHP default timezone may be UTC on servers; always interpret app datetimes in config timezone.
 */
final class MailIstTime
{
    public const DEFAULT_TZ = 'Asia/Kolkata';

    private static bool $defaultTzApplied = false;

    private static ?string $cachedTzId = null;

    /**
     * Call once per web request (index.php) or before sending mail from CLI scripts.
     *
     * @param array<string,mixed>|null $appConfig Optional config row (must include 'timezone' when provided).
     */
    public static function ensureDefaultTimezone(?array $appConfig = null): void
    {
        if (self::$defaultTzApplied) {
            return;
        }
        date_default_timezone_set(self::timezoneId($appConfig));
        self::$defaultTzApplied = true;
    }

    /**
     * @param array<string,mixed>|null $appConfig
     */
    public static function timezoneId(?array $appConfig = null): string
    {
        if ($appConfig !== null && !empty($appConfig['timezone']) && is_string($appConfig['timezone'])) {
            return $appConfig['timezone'];
        }
        if (self::$cachedTzId !== null) {
            return self::$cachedTzId;
        }
        $path = dirname(__DIR__, 2) . '/config/app.php';
        if (is_file($path)) {
            $cfg = require $path;
            if (!empty($cfg['timezone']) && is_string($cfg['timezone'])) {
                self::$cachedTzId = $cfg['timezone'];

                return self::$cachedTzId;
            }
        }
        self::$cachedTzId = self::DEFAULT_TZ;

        return self::$cachedTzId;
    }

    /**
     * Single-line stamp for mail headers and footers (always IST).
     *
     * @param array<string,mixed>|null $appConfig
     */
    public static function formatMailStampNow(?array $appConfig = null): string
    {
        $tz = new \DateTimeZone(self::timezoneId($appConfig));

        return (new \DateTimeImmutable('now', $tz))->format('M j, Y g:i A') . ' IST';
    }

    /**
     * MySQL datetime string (naive, stored in application time) or ISO-UTC with Z.
     *
     * @param array<string,mixed>|null $appConfig
     */
    public static function formatDbDateTime(?string $mysql, ?array $appConfig = null, string $pattern = 'M j, Y g:i A'): string
    {
        if ($mysql === null || trim($mysql) === '') {
            return '';
        }
        $mysql = trim($mysql);
        $tzId = self::timezoneId($appConfig);
        $localTz = new \DateTimeZone($tzId);
        try {
            if (preg_match('/Z$/i', $mysql)) {
                $dt = new \DateTimeImmutable($mysql);
                $dt = $dt->setTimezone($localTz);
            } else {
                $dt = new \DateTimeImmutable($mysql, $localTz);
            }

            return $dt->format($pattern) . ' IST';
        } catch (\Throwable $e) {
            return $mysql;
        }
    }

    /**
     * Calendar date (Y-m-d) display in IST context — avoids UTC midnight off-by-one.
     *
     * @param array<string,mixed>|null $appConfig
     */
    public static function formatDateOnly(?string $ymd, ?array $appConfig = null, string $pattern = 'M j, Y'): string
    {
        if ($ymd === null || $ymd === '') {
            return '';
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}/', (string) $ymd)) {
            return '';
        }
        $tz = new \DateTimeZone(self::timezoneId($appConfig));
        $dt = new \DateTimeImmutable(substr((string) $ymd, 0, 10) . ' 12:00:00', $tz);

        return $dt->format($pattern);
    }

    /** Current year-month (Y-m) in app timezone. */
    public static function yearMonthNow(?array $appConfig = null): string
    {
        $tz = new \DateTimeZone(self::timezoneId($appConfig));

        return (new \DateTimeImmutable('now', $tz))->format('Y-m');
    }

    /** Shift a Y-m string by signed months (calendar arithmetic in app TZ). */
    public static function shiftYearMonth(string $ym, int $deltaMonths, ?array $appConfig = null): string
    {
        $ym = trim($ym);
        if (!preg_match('/^\d{4}-\d{2}$/', $ym)) {
            return self::yearMonthNow($appConfig);
        }
        $tz = new \DateTimeZone(self::timezoneId($appConfig));
        $dt = new \DateTimeImmutable($ym . '-01 12:00:00', $tz);
        $sign = $deltaMonths >= 0 ? '+' : '-';

        return $dt->modify($sign . abs($deltaMonths) . ' months')->format('Y-m');
    }

    /**
     * First and last calendar day (Y-m-d) of the month containing $refYmd (any day in that month).
     *
     * @return array{0:string,1:string}
     */
    public static function monthBoundsYmd(?string $refYmd = null, ?array $appConfig = null): array
    {
        $ymd = $refYmd !== null ? substr(trim($refYmd), 0, 10) : self::todayYmd($appConfig);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) {
            $ymd = self::todayYmd($appConfig);
        }
        $tz = new \DateTimeZone(self::timezoneId($appConfig));
        $dt = new \DateTimeImmutable($ymd . ' 12:00:00', $tz);
        $first = $dt->modify('first day of this month')->format('Y-m-d');
        $last = $dt->modify('last day of this month')->format('Y-m-d');

        return [$first, $last];
    }

    /** First day of the month that is $monthOffset months from the month containing “today” (e.g. -5 → six-month window start). */
    public static function firstDayOfMonthOffsetFromToday(int $monthOffset, ?array $appConfig = null): string
    {
        $tz = new \DateTimeZone(self::timezoneId($appConfig));
        $base = new \DateTimeImmutable(self::todayYmd($appConfig) . ' 12:00:00', $tz);
        $firstThis = $base->modify('first day of this month');
        if ($monthOffset === 0) {
            return $firstThis->format('Y-m-d');
        }
        $sign = $monthOffset > 0 ? '+' : '-';

        return $firstThis->modify($sign . abs($monthOffset) . ' months')->format('Y-m-d');
    }

    /** Last day of month for a Y-m string (e.g. trend / bar-chart links). */
    public static function lastDayOfYm(string $ym, ?array $appConfig = null): string
    {
        $ym = trim($ym);
        if (!preg_match('/^\d{4}-\d{2}$/', $ym)) {
            [, $last] = self::monthBoundsYmd(null, $appConfig);

            return $last;
        }
        [, $last] = self::monthBoundsYmd($ym . '-01', $appConfig);

        return $last;
    }

    /** Day-of-week for first day of month (0 = Sunday … 6 = Saturday), app TZ. */
    public static function firstWeekdayOfYm(string $ym, ?array $appConfig = null): int
    {
        $ym = trim($ym);
        if (!preg_match('/^\d{4}-\d{2}$/', $ym)) {
            $ym = self::yearMonthNow($appConfig);
        }
        $tz = new \DateTimeZone(self::timezoneId($appConfig));
        $dt = new \DateTimeImmutable($ym . '-01 12:00:00', $tz);

        return (int) $dt->format('w');
    }

    /** Number of days in month for Y-m. */
    public static function daysInMonthYm(string $ym, ?array $appConfig = null): int
    {
        $ym = trim($ym);
        if (!preg_match('/^\d{4}-\d{2}$/', $ym)) {
            $ym = self::yearMonthNow($appConfig);
        }
        $tz = new \DateTimeZone(self::timezoneId($appConfig));
        $dt = new \DateTimeImmutable($ym . '-01 12:00:00', $tz);

        return (int) $dt->format('t');
    }

    /** Add signed calendar days to a Y-m-d date in app timezone (avoids UTC drift). */
    public static function shiftCalendarDays(string $ymd, int $days, ?array $appConfig = null): string
    {
        $ymd = substr(trim($ymd), 0, 10);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) {
            return self::todayYmd($appConfig);
        }
        $tz = new \DateTimeZone(self::timezoneId($appConfig));

        return (new \DateTimeImmutable($ymd . ' 12:00:00', $tz))->modify(($days >= 0 ? '+' : '') . $days . ' days')->format('Y-m-d');
    }

    /** Today’s calendar date (Y-m-d) in app timezone — use for overdue logic and list filters. */
    public static function todayYmd(?array $appConfig = null): string
    {
        $tz = new \DateTimeZone(self::timezoneId($appConfig));

        return (new \DateTimeImmutable('now', $tz))->format('Y-m-d');
    }

    /**
     * Calendar days from due date to today (IST); 0 if due today or in the future.
     */
    public static function wholeCalendarDaysPastDue(string $dueYmd, ?array $appConfig = null): int
    {
        $dueYmd = substr(trim($dueYmd), 0, 10);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueYmd)) {
            return 0;
        }
        $today = self::todayYmd($appConfig);
        if ($dueYmd >= $today) {
            return 0;
        }
        $tz = new \DateTimeZone(self::timezoneId($appConfig));
        $due = new \DateTimeImmutable($dueYmd . ' 00:00:00', $tz);
        $now = new \DateTimeImmutable($today . ' 00:00:00', $tz);

        return max(0, (int) floor(($now->getTimestamp() - $due->getTimestamp()) / 86400));
    }

    /** Compliance UI: calendar date or em dash. */
    public static function formatUiDate(?string $ymd, ?array $appConfig = null, string $pattern = 'M j, Y'): string
    {
        if ($ymd === null || trim((string) $ymd) === '') {
            return '—';
        }
        $f = self::formatDateOnly(substr((string) $ymd, 0, 10), $appConfig, $pattern);

        return $f !== '' ? $f : '—';
    }

    /** Compliance UI: stored datetime shown in IST with suffix. */
    public static function formatUiDateTime(?string $mysql, ?array $appConfig = null): string
    {
        if ($mysql === null || trim((string) $mysql) === '') {
            return '—';
        }
        $s = self::formatDbDateTime(trim((string) $mysql), $appConfig, 'M j, Y, g:i A');

        return $s !== '' ? $s : '—';
    }
}
