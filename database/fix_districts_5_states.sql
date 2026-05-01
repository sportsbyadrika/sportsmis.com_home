-- Idempotent: districts for TN, PY, AP, GA, KA. Safe to re-run.
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
