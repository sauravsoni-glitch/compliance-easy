-- Migration 024: Add IRDAI to authorities (id sequence follows upstream migrations)
INSERT INTO authorities (name)
SELECT 'IRDAI' WHERE NOT EXISTS (SELECT 1 FROM authorities WHERE name = 'IRDAI');
