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
('Athletics'),('Badminton'),('Baseball'),('Basketball'),('Boxing'),('Chess'),
('Cricket'),('Cycling'),('Football'),('Gymnastics'),('Hockey'),
('Judo'),('Kabaddi'),('Karate'),('Kho Kho'),('Shooting'),
('Squash'),('Swimming'),('Table Tennis'),('Tennis'),('Volleyball'),
('Weightlifting'),('Wrestling'),('Yoga');

-- -------------------------------------------------------
-- Age Categories
-- -------------------------------------------------------
INSERT IGNORE INTO age_categories (name, min_age, max_age, sort_order) VALUES
('Sub Youth',     NULL,  14, 1),
('Youth',           14,  17, 2),
('Junior',          17,  20, 3),
('Senior',          20,  35, 4),
('Master',          35,  50, 5),
('Senior Master',   50, NULL, 6);

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

-- -------------------------------------------------------
-- Tamil Nadu Districts (38)
-- -------------------------------------------------------
INSERT IGNORE INTO districts (state_id, name)
SELECT s.id, d.name FROM states s
CROSS JOIN (
  SELECT 'Ariyalur' AS name UNION SELECT 'Chengalpattu' UNION SELECT 'Chennai'
  UNION SELECT 'Coimbatore' UNION SELECT 'Cuddalore' UNION SELECT 'Dharmapuri'
  UNION SELECT 'Dindigul' UNION SELECT 'Erode' UNION SELECT 'Kallakurichi'
  UNION SELECT 'Kanchipuram' UNION SELECT 'Kanyakumari' UNION SELECT 'Karur'
  UNION SELECT 'Krishnagiri' UNION SELECT 'Madurai' UNION SELECT 'Mayiladuthurai'
  UNION SELECT 'Nagapattinam' UNION SELECT 'Namakkal' UNION SELECT 'Nilgiris'
  UNION SELECT 'Perambalur' UNION SELECT 'Pudukkottai' UNION SELECT 'Ramanathapuram'
  UNION SELECT 'Ranipet' UNION SELECT 'Salem' UNION SELECT 'Sivaganga'
  UNION SELECT 'Tenkasi' UNION SELECT 'Thanjavur' UNION SELECT 'Theni'
  UNION SELECT 'Thoothukudi' UNION SELECT 'Tiruchirappalli' UNION SELECT 'Tirunelveli'
  UNION SELECT 'Tirupathur' UNION SELECT 'Tiruppur' UNION SELECT 'Tiruvallur'
  UNION SELECT 'Tiruvannamalai' UNION SELECT 'Tiruvarur' UNION SELECT 'Vellore'
  UNION SELECT 'Viluppuram' UNION SELECT 'Virudhunagar'
) d WHERE s.code = 'TN' AND s.country_id = 1;

-- -------------------------------------------------------
-- Puducherry Districts (4)
-- -------------------------------------------------------
INSERT IGNORE INTO districts (state_id, name)
SELECT s.id, d.name FROM states s
CROSS JOIN (
  SELECT 'Puducherry' AS name UNION SELECT 'Karaikal'
  UNION SELECT 'Mahe' UNION SELECT 'Yanam'
) d WHERE s.code = 'PY' AND s.country_id = 1;

-- -------------------------------------------------------
-- Andhra Pradesh Districts (26)
-- -------------------------------------------------------
INSERT IGNORE INTO districts (state_id, name)
SELECT s.id, d.name FROM states s
CROSS JOIN (
  SELECT 'Alluri Sitharama Raju' AS name UNION SELECT 'Anakapalli'
  UNION SELECT 'Ananthapuramu' UNION SELECT 'Annamayya' UNION SELECT 'Bapatla'
  UNION SELECT 'Chittoor' UNION SELECT 'Dr. B. R. Ambedkar Konaseema'
  UNION SELECT 'East Godavari' UNION SELECT 'Eluru' UNION SELECT 'Guntur'
  UNION SELECT 'Kadapa' UNION SELECT 'Kakinada' UNION SELECT 'Krishna'
  UNION SELECT 'Kurnool' UNION SELECT 'Nandyal' UNION SELECT 'NTR'
  UNION SELECT 'Palnadu' UNION SELECT 'Parvathipuram Manyam' UNION SELECT 'Prakasam'
  UNION SELECT 'Sri Potti Sriramulu Nellore' UNION SELECT 'Sri Sathya Sai'
  UNION SELECT 'Srikakulam' UNION SELECT 'Tirupati' UNION SELECT 'Visakhapatnam'
  UNION SELECT 'Vizianagaram' UNION SELECT 'West Godavari'
) d WHERE s.code = 'AP' AND s.country_id = 1;

-- -------------------------------------------------------
-- Goa Districts (2)
-- -------------------------------------------------------
INSERT IGNORE INTO districts (state_id, name)
SELECT s.id, d.name FROM states s
CROSS JOIN (
  SELECT 'North Goa' AS name UNION SELECT 'South Goa'
) d WHERE s.code = 'GA' AND s.country_id = 1;

-- -------------------------------------------------------
-- Karnataka Districts (31)
-- -------------------------------------------------------
INSERT IGNORE INTO districts (state_id, name)
SELECT s.id, d.name FROM states s
CROSS JOIN (
  SELECT 'Bagalkot' AS name UNION SELECT 'Ballari' UNION SELECT 'Belagavi'
  UNION SELECT 'Bengaluru Rural' UNION SELECT 'Bengaluru Urban'
  UNION SELECT 'Bidar' UNION SELECT 'Chamarajanagar' UNION SELECT 'Chikkaballapur'
  UNION SELECT 'Chikkamagaluru' UNION SELECT 'Chitradurga' UNION SELECT 'Dakshina Kannada'
  UNION SELECT 'Davanagere' UNION SELECT 'Dharwad' UNION SELECT 'Gadag'
  UNION SELECT 'Hassan' UNION SELECT 'Haveri' UNION SELECT 'Kalaburagi'
  UNION SELECT 'Kodagu' UNION SELECT 'Kolar' UNION SELECT 'Koppal'
  UNION SELECT 'Mandya' UNION SELECT 'Mysuru' UNION SELECT 'Raichur'
  UNION SELECT 'Ramanagara' UNION SELECT 'Shivamogga' UNION SELECT 'Tumakuru'
  UNION SELECT 'Udupi' UNION SELECT 'Uttara Kannada' UNION SELECT 'Vijayanagara'
  UNION SELECT 'Vijayapura' UNION SELECT 'Yadgir'
) d WHERE s.code = 'KA' AND s.country_id = 1;
