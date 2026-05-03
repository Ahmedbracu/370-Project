-- ============================================================
--  SmartNav SQL Patch — run this in phpMyAdmin SQL tab
--  Run ONCE after your original schema is already created
-- ============================================================

-- 1. Add 'role' column to user table (needed for admin login)
ALTER TABLE user ADD COLUMN role VARCHAR(20) DEFAULT 'user' AFTER comfort_level;

-- 2. Sample locations (Dhaka) — skip if already added
INSERT IGNORE INTO location (location_name, latitude, longitude, area_zone) VALUES
('Dhanmondi',        23.74623, 90.37456, 'Central'),
('Gulshan',          23.78060, 90.41445, 'North'),
('Motijheel',        23.72892, 90.41981, 'Commercial'),
('Mirpur',           23.80609, 90.36454, 'North-West'),
('Uttara',           23.87401, 90.39928, 'North'),
('Banani',           23.79369, 90.40418, 'North'),
('Mohammadpur',      23.76279, 90.35698, 'West'),
('Rampura',          23.75780, 90.43200, 'East'),
('Farmgate',         23.75776, 90.38900, 'Central'),
('Shahbagh',         23.73826, 90.39568, 'Central');

-- 3. Sample transport modes — skip if already added
INSERT IGNORE INTO transport_mode (transport_type, average_speed, base_fare) VALUES
('Bus',      25.0, 10.00),
('CNG',      30.0, 40.00),
('Rickshaw', 12.0, 20.00),
('Metro',    45.0, 20.00),
('Uber',     35.0, 50.00);

-- 4. Sample routes
INSERT IGNORE INTO route (source_location_id, destination_location_id, total_distance, estimated_time, estimated_cost) VALUES
(1, 2,  8.5,  35, 80.00),
(1, 3,  6.2,  25, 55.00),
(2, 5,  9.0,  30, 70.00),
(4, 1, 10.3,  45, 90.00),
(9, 10, 1.8,  10, 25.00),
(1, 9,  2.5,  12, 30.00);

-- 5. Sample route segments (multimodal example: Dhanmondi -> Gulshan via Bus then CNG)
INSERT IGNORE INTO route_segment (route_id, transport_id, start_location_id, end_location_id, segment_distance, segment_time, segment_cost) VALUES
(1, 1, 1, 9, 2.5, 15, 10.00),
(1, 2, 9, 2, 6.0, 20, 70.00);

-- 6. Make admin user (after registering, run this to upgrade yourself)
-- UPDATE user SET role='admin' WHERE email='your@email.com';

-- 7. Sample traffic data
INSERT IGNORE INTO traffic_data (location_id, congestion_level, avg_speed, date, time_slot) VALUES
(1, 'Heavy',    18.5, CURDATE(), '08:00:00'),
(2, 'Moderate', 28.0, CURDATE(), '08:00:00'),
(3, 'Gridlock', 8.0,  CURDATE(), '09:00:00'),
(1, 'Gridlock', 6.5,  CURDATE(), '17:00:00'),
(2, 'Heavy',    15.0, CURDATE(), '17:00:00'),
(5, 'Clear',    42.0, CURDATE(), '10:00:00');

-- 8. Sample incident
INSERT IGNORE INTO incident_report (user_id, location_id, incident_type, severity, status) VALUES
(1, 3, 'Traffic Jam', 'High',   'Active'),
(1, 1, 'Road Work',   'Medium', 'Active');
