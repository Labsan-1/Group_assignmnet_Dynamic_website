<?php
// Register.php — strong validation + email verification

session_start();
include 'db.php';
require_once 'mailer.php';

// Security questions
$security_questions = [
    "What was the name of your first pet?" => "What was the name of your first pet?",
    "In which city were you born?" => "In which city were you born?",
    "What is your mother's maiden name?" => "What is your mother's maiden name?",
    "What was your favorite teacher's name?" => "What was your favorite teacher's name?",
    "What was the name of your first school?" => "What was the name of your first school?"
];

// Helper
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Initialize values so we can repopulate the form on errors
$values = [
    'username' => '',
    'email'    => '',
    'address'  => '',
    'ph_no'    => '',
    'security_question' => '',
    'security_answer'   => ''
];
$errors = [];
$success_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['username'] = trim($_POST['username'] ?? '');
    $values['email']    = trim($_POST['email'] ?? '');
    $password           = $_POST['password'] ?? '';
    $values['address']  = trim($_POST['address'] ?? '');
    $values['ph_no']    = trim($_POST['ph_no'] ?? '');
    $values['security_question'] = trim($_POST['security_question'] ?? '');
    $values['security_answer']   = trim($_POST['security_answer'] ?? '');

    // Normalize phone to digits only for validation/storage
    $phone_digits = preg_replace('/\D+/', '', $values['ph_no']);

    // Validations
    if (!preg_match('/^[a-zA-Z0-9]{3,20}$/', $values['username'])) {
        $errors[] = "Username should be alphanumeric and 3–20 characters long.";
    }
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }
    if (!preg_match('/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[\W_]).{8,}$/', $password)) {
        $errors[] = "Password must be at least 8 characters, with one uppercase, one lowercase, one number, and one special character.";
    }
    if (!preg_match('/^\d{10}$/', $phone_digits)) {
        $errors[] = "Phone number must be exactly 10 digits.";
    }
    if ($values['address'] === '') {
        $errors[] = "Address cannot be empty.";
    }
    if ($values['security_question'] === '' || !array_key_exists($values['security_question'], $security_questions)) {
        $errors[] = "Please select a valid security question.";
    }
    if ($values['security_answer'] === '') {
        $errors[] = "Security answer cannot be empty.";
    }

    if (empty($errors)) {
        // Check uniqueness
        if ($stmt = $con->prepare("SELECT 1 FROM users WHERE username = ? OR email = ? LIMIT 1")) {
            $stmt->bind_param("ss", $values['username'], $values['email']);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $errors[] = "Username or email is already taken.";
            }
            $stmt->close();
        } else {
            $errors[] = "Error preparing uniqueness check: " . $con->error;
        }
    }

    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $hashed_security_answer = password_hash($values['security_answer'], PASSWORD_DEFAULT);
        $verify_token = bin2hex(random_bytes(16));

        // Store digits-only phone before insert
        $values['ph_no'] = $phone_digits;

        if ($stmt = $con->prepare("INSERT INTO users 
            (username, email, password, Address, ph_no, security_question, security_answer, is_verified, verify_token)
            VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?)")) {
            $stmt->bind_param(
                "ssssssss",
                $values['username'],
                $values['email'],
                $hashed_password,
                $values['address'],
                $values['ph_no'],
                $values['security_question'],
                $hashed_security_answer,
                $verify_token
            );
            if ($stmt->execute()) {
                // Send verification email (returns true on success or an error string)
                $result = sendVerificationEmail($values['email'], $values['username'], $verify_token);
                if ($result === true) {
                    $success_msg = "✅ Registration successful! Check your email to verify your account.";
                    // Optionally clear the form
                    $values = array_map(fn() => '', $values);
                } else {
                    // Show the real mailer error to help you fix config quickly
                    $errors[] = "Email error: " . h($result);
                }
            } else {
                $errors[] = "Error inserting user: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $errors[] = "Error preparing insert: " . $con->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Register</title>
  <link rel="stylesheet" href="./Styles/register.css"/>
  <style>
    .error-box{background:#ffecec;color:#c00;border:1px solid #f3b5b5;border-radius:10px;padding:12px 16px;margin:12px 0;}
    .error-box ul{margin:0 0 0 18px}
    .success-box{background:#ecffef;color:#0a7b2f;border:1px solid #b8f0c9;border-radius:10px;padding:12px 16px;margin:12px 0;}
    .form-grid{display:grid;gap:12px}
    .form-group{display:flex;flex-direction:column}
    .labels{font-weight:600;margin-bottom:4px}
  </style>
  <script>
    function validateForm(){
      const u  = document.getElementById('username').value.trim();
      const e  = document.getElementById('email').value.trim();
      const p  = document.getElementById('password').value;
      const a  = document.getElementById('address').value.trim();
      const raw= document.getElementById('ph_no').value;
      const ph = raw.replace(/\D+/g,''); // normalize digits only
      const q  = document.getElementById('security_question').value;
      const sa = document.getElementById('security_answer').value.trim();

      const errs = [];

      if (!/^[a-zA-Z0-9]{3,20}$/.test(u)) errs.push('Username should be alphanumeric and 3–20 characters long.');
      if (!/^[^@]+@[^@]+\.[a-zA-Z]{2,}$/.test(e)) errs.push('Invalid email format.');
      if (!/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[\W_]).{8,}$/.test(p)) errs.push('Password must be at least 8 chars with upper, lower, number, special.');
      if (!/^\d{10}$/.test(ph)) errs.push('Phone must be exactly 10 digits.');
      if (a==='') errs.push('Address cannot be empty.');
      if (q==='') errs.push('Please select a security question.');
      if (sa==='') errs.push('Security answer cannot be empty.');

      if (errs.length){
        alert(errs.join('\n'));
        return false;
      }

      // write normalized phone back so server gets digits only
      document.getElementById('ph_no').value = ph;
      return true;
    }
  </script>
</head>
<body>
  <div class="login-container">
    <form action="Register.php" method="POST" onsubmit="return validateForm()">
      <h1>Register Here</h1>

      <?php if(!empty($success_msg)): ?>
        <div class="success-box"><?php echo h($success_msg); ?></div>
      <?php endif; ?>

      <?php if(!empty($errors)): ?>
        <div class="error-box"><ul>
          <?php foreach($errors as $msg): ?>
            <li><?php echo h($msg); ?></li>
          <?php endforeach; ?>
        </ul></div>
      <?php endif; ?>

      <div class="form-grid">
        <div class="form-group">
          <label class="labels" for="username">User Name</label>
          <input type="text" id="username" name="username" required value="<?php echo h($values['username']); ?>">
        </div>

        <div class="form-group">
          <label class="labels" for="email">Email</label>
          <input type="email" id="email" name="email" required value="<?php echo h($values['email']); ?>">
        </div>

        <div class="form-group">
          <label class="labels" for="password">Password</label>
          <input type="password" id="password" name="password" required placeholder="Min 8 chars, Aa1!" autocomplete="new-password">
        </div>

        <div class="form-group">
          <label class="labels" for="address">Address</label>
          <input type="text" id="address" name="address" required value="<?php echo h($values['address']); ?>">
        </div>

        <div class="form-group">
          <label class="labels" for="ph_no">Phone Number</label>
          <input type="text" id="ph_no" name="ph_no" required inputmode="numeric" maxlength="14" value="<?php echo h($values['ph_no']); ?>" placeholder="10 digits only">
        </div>

        <div class="form-group">
          <label class="labels" for="security_question">Security Question</label>
          <select id="security_question" name="security_question" required>
            <option value="">Select a Security Question</option>
            <?php foreach($security_questions as $q): ?>
              <option value="<?php echo h($q); ?>" <?php echo ($values['security_question']===$q)?'selected':''; ?>>
                <?php echo h($q); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label class="labels" for="security_answer">Security Answer</label>
          <input type="text" id="security_answer" name="security_answer" required value="<?php echo h($values['security_answer']); ?>">
        </div>

        <input type="submit" value="Register">
        <div class="register-link" style="margin-top:8px;">
          Already Registered? <a href="Login.php">Login Now</a>
        </div>
      </div>
    </form>
  </div>
</body>
</html>
