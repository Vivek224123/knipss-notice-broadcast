-- Sirf zaruri columns ka view (Aadhaar, bank, caste jaisa data app ko nahi milega)
CREATE OR REPLACE VIEW v_broadcast_students AS
SELECT sno, stu_name, class, batch, whatsapp_no, mobile, parent_mobile, stu_status, cancel_date
FROM student_info;

CREATE TABLE IF NOT EXISTS notices (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(200) NOT NULL,
  body TEXT NOT NULL,
  target_class VARCHAR(100),
  target_batch VARCHAR(100),
  total INT DEFAULT 0,
  status VARCHAR(20) DEFAULT 'sending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS message_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  notice_id INT NOT NULL,
  sno INT,
  phone VARCHAR(20),
  wamid VARCHAR(100),
  status VARCHAR(20),
  error VARCHAR(300),
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (notice_id), INDEX (wamid)
) CHARSET=utf8mb4;
