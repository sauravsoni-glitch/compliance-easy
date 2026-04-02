-- Point all compliances at the lowest id per authority name, then remove duplicate authority rows.
SET NAMES utf8mb4;

UPDATE `compliances` c
INNER JOIN `authorities` a ON a.id = c.authority_id
INNER JOIN (
    SELECT `name`, MIN(`id`) AS keep_id FROM `authorities` GROUP BY `name`
) k ON k.`name` = a.`name` AND a.id != k.keep_id
SET c.authority_id = k.keep_id;

DELETE a FROM `authorities` a
INNER JOIN (
    SELECT `name`, MIN(`id`) AS keep_id FROM `authorities` GROUP BY `name`
) k ON k.`name` = a.`name`
WHERE a.id != k.keep_id;
