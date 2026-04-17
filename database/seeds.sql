-- SportsMIS – Seed Data
SET NAMES utf8mb4;

-- -------------------------------------------------------
-- Super Admin User (change password immediately after first login)
-- password: Admin@123 (bcrypt)
-- -------------------------------------------------------
INSERT IGNORE INTO users (email, password, role, status, email_verified_at) VALUES
('admin@sportsmis.com', '$2y$12$HfpsNHF4Gfh4S26LGA4vU./FxutgrSW0fsA5JYXAd2D23xza0cnsW', 'super_admin', 'active', NOW());

-- -------------------------------------------------------
-- Institution Types
-- -------------------------------------------------------
INSERT IGNORE INTO institution_types (name, sort_order) VALUES
('School',             1),
('College / University', 2),
('Sports Academy',    3),
('Sports Club',       4),
('District Sports Association', 5),
('State Sports Association',   6),
('National Federation',        7),
('Other',             99);

-- -------------------------------------------------------
-- Sports
-- -------------------------------------------------------
INSERT IGNORE INTO sports (name) VALUES
('Athletics'),('Badminton'),('Basketball'),('Boxing'),('Chess'),
('Cricket'),('Cycling'),('Football'),('Gymnastics'),('Hockey'),
('Judo'),('Kabaddi'),('Karate'),('Kho Kho'),('Shooting'),
('Squash'),('Swimming'),('Table Tennis'),('Tennis'),('Volleyball'),
('Weightlifting'),('Wrestling'),('Yoga');

-- -------------------------------------------------------
-- Staff Roles
-- -------------------------------------------------------
INSERT IGNORE INTO staff_roles (name, slug, description) VALUES
('Registration Desk',    'registration_desk',    'Handles athlete check-in and on-site registration'),
('Field Referee',        'field_referee',         'Officiates matches and events on field'),
('Score Entry',          'score_entry',           'Enters scores/results into the system'),
('Score Approval',       'score_approval',        'Reviews and approves entered scores'),
('Certificate Generation','certificate_generation','Generates and prints participation/winner certificates');

-- -------------------------------------------------------
-- ID Proof Types
-- -------------------------------------------------------
INSERT IGNORE INTO id_proof_types (name) VALUES
('Aadhaar Card'),('PAN Card'),('Passport'),('Voter ID'),
('Driving Licence'),('School ID Card'),('College ID Card'),('Other');

-- -------------------------------------------------------
-- Countries (India first, then key countries)
-- -------------------------------------------------------
INSERT IGNORE INTO countries (id, name, iso2, phone_code) VALUES
(1,  'India',          'IN', '+91'),
(2,  'United States',  'US', '+1'),
(3,  'United Kingdom', 'GB', '+44'),
(4,  'Australia',      'AU', '+61'),
(5,  'Canada',         'CA', '+1'),
(6,  'Germany',        'DE', '+49'),
(7,  'France',         'FR', '+33'),
(8,  'Japan',          'JP', '+81'),
(9,  'China',          'CN', '+86'),
(10, 'Brazil',         'BR', '+55'),
(11, 'South Africa',   'ZA', '+27'),
(12, 'Sri Lanka',      'LK', '+94'),
(13, 'Bangladesh',     'BD', '+880'),
(14, 'Pakistan',       'PK', '+92'),
(15, 'Nepal',          'NP', '+977'),
(16, 'Bhutan',         'BT', '+975'),
(17, 'Maldives',       'MV', '+960'),
(18, 'Myanmar',        'MM', '+95'),
(19, 'Malaysia',       'MY', '+60'),
(20, 'Singapore',      'SG', '+65');

-- -------------------------------------------------------
-- Indian States
-- -------------------------------------------------------
INSERT IGNORE INTO states (country_id, name, code) VALUES
(1,'Andhra Pradesh','AP'),(1,'Arunachal Pradesh','AR'),(1,'Assam','AS'),
(1,'Bihar','BR'),(1,'Chhattisgarh','CG'),(1,'Goa','GA'),
(1,'Gujarat','GJ'),(1,'Haryana','HR'),(1,'Himachal Pradesh','HP'),
(1,'Jharkhand','JH'),(1,'Karnataka','KA'),(1,'Kerala','KL'),
(1,'Madhya Pradesh','MP'),(1,'Maharashtra','MH'),(1,'Manipur','MN'),
(1,'Meghalaya','ML'),(1,'Mizoram','MZ'),(1,'Nagaland','NL'),
(1,'Odisha','OD'),(1,'Punjab','PB'),(1,'Rajasthan','RJ'),
(1,'Sikkim','SK'),(1,'Tamil Nadu','TN'),(1,'Telangana','TG'),
(1,'Tripura','TR'),(1,'Uttar Pradesh','UP'),(1,'Uttarakhand','UK'),
(1,'West Bengal','WB'),
-- UTs
(1,'Andaman & Nicobar Islands','AN'),(1,'Chandigarh','CH'),
(1,'Dadra & Nagar Haveli and Daman & Diu','DN'),(1,'Delhi','DL'),
(1,'Jammu & Kashmir','JK'),(1,'Ladakh','LA'),
(1,'Lakshadweep','LD'),(1,'Puducherry','PY');

-- -------------------------------------------------------
-- Kerala Districts (sample – extend similarly for all states)
-- -------------------------------------------------------
INSERT IGNORE INTO districts (state_id, name)
SELECT s.id, d.name FROM states s
CROSS JOIN (
  SELECT 'Alappuzha' AS name UNION SELECT 'Ernakulam' UNION SELECT 'Idukki'
  UNION SELECT 'Kannur' UNION SELECT 'Kasaragod' UNION SELECT 'Kollam'
  UNION SELECT 'Kottayam' UNION SELECT 'Kozhikode' UNION SELECT 'Malappuram'
  UNION SELECT 'Palakkad' UNION SELECT 'Pathanamthitta' UNION SELECT 'Thiruvananthapuram'
  UNION SELECT 'Thrissur' UNION SELECT 'Wayanad'
) d WHERE s.code = 'KL' AND s.country_id = 1;
