<?php
/** @var string $orgFlowPhase profile | invite | done */
$phase = $orgFlowPhase ?? 'profile';
$s1 = 'todo';
$s2 = 'todo';
$s3 = 'todo';
$line12 = false;
$line23 = false;
if ($phase === 'profile') {
    $s1 = 'current';
} elseif ($phase === 'invite') {
    $s1 = 'done';
    $s2 = 'current';
    $line12 = true;
} else {
    $s1 = 'done';
    $s2 = 'done';
    $s3 = 'done';
    $line12 = true;
    $line23 = true;
}
function org_p_circle(string $st, int $n): void {
    if ($st === 'done') {
        echo '<div class="org-p-circle org-p-done"><i class="fas fa-check"></i></div>';
    } elseif ($st === 'current') {
        echo '<div class="org-p-circle org-p-current">' . $n . '</div>';
    } else {
        echo '<div class="org-p-circle org-p-todo">' . $n . '</div>';
    }
}
?>
<div class="org-process" aria-label="Setup progress">
    <div class="org-process-inner">
        <div class="org-p-step <?= $s1 === 'current' ? 'org-p-step--on' : ($s1 === 'done' ? 'org-p-step--ok' : '') ?>">
            <?php org_p_circle($s1, 1); ?>
            <span class="org-p-label">Organization Profile</span>
        </div>
        <div class="org-p-connector <?= $line12 ? 'org-p-connector--on' : '' ?>" aria-hidden="true"></div>
        <div class="org-p-step <?= $s2 === 'current' ? 'org-p-step--on' : ($s2 === 'done' ? 'org-p-step--ok' : '') ?>">
            <?php org_p_circle($s2, 2); ?>
            <span class="org-p-label">Invite Users</span>
        </div>
        <div class="org-p-connector <?= $line23 ? 'org-p-connector--on' : '' ?>" aria-hidden="true"></div>
        <div class="org-p-step <?= $s3 === 'current' ? 'org-p-step--on' : ($s3 === 'done' ? 'org-p-step--ok' : '') ?>">
            <?php org_p_circle($s3, 3); ?>
            <span class="org-p-label">Completed</span>
        </div>
    </div>
</div>
