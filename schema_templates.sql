-- Isi database me chalao jisme notices / message_logs / student_info hai
CREATE TABLE IF NOT EXISTS wa_templates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  heading       VARCHAR(150) NOT NULL,            -- list me dikhne wala naam
  template_name VARCHAR(100) NOT NULL,            -- Meta me approved template ka exact naam
  lang          VARCHAR(10)  NOT NULL DEFAULT 'en',
  body          TEXT NOT NULL,                    -- Meta jaisi body, variables {{1}}, {{2}} ...
  active        TINYINT NOT NULL DEFAULT 1
) CHARSET=utf8mb4;

-- Example (templates.php page se bhi add kar sakte ho):
-- INSERT INTO wa_templates (heading, template_name, lang, body) VALUES
-- ('College Notice', 'college_notice', 'en', 'Namaste {{1}}, KNIPSS ki taraf se naya notice: {{2}}. {{3}} - KNIPSS');
