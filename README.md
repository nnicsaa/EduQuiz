EDUQuiz!
EDUQuiz is a responsive, web-based educational quiz platform built with PHP and MySQL. It features a modern, glassmorphism-inspired UI and provides a comprehensive system for both students and administrators to engage with, track, and manage quizzes.

🚀 Features
🎓 For Students (Dashboard)
Interactive Quiz Taking: Browse open quiz modules and take multiple-choice tests.

Progress Tracking: View detailed history of past attempts, raw scores, and performance percentages.

Dynamic Leaderboard: Compete with peers; leaderboard ranks students based on cumulative points.

Gamification: Daily streak tracking to encourage consistent learning.

Profile Management: Customize display nicknames and upload profile avatars.

🛡️ For Administrators (Admin Panel)
Analytics Overview: Quick metrics on total students, quizzes, questions, and account statuses.

Account Management: Approve pending registrations, deactivate accounts, lock out failed attempts, and view individual student performance records.

Quiz Management: Create new quiz categories, add multiple-choice questions, and toggle quiz availability (Open/Closed).

Automated Reporting: Generate performance reports for specific quiz modules, showing best scores and passing rates.

Data Control: Safely cascade-delete quizzes along with their associated questions and student records.

🛠️ Tech Stack
Frontend: HTML5, Custom CSS3 (CSS Variables, Flexbox/Grid, Mobile Responsive Media Queries), Vanilla JavaScript.

Backend: PHP (Session-based authentication, Prepared Statements for SQL injection prevention).

Database: MySQL.

Environment: XAMPP (Local), InfinityFree (Production).

📂 Project Structure (Key Files)
Plaintext
/finalproject
├── index.php             # Main student dashboard / UI
├── admindashboard.php    # Administrator control panel
├── register.php          # User registration & login gateway
├── db.php                # MySQL database connection configuration
├── header.php            # Shared HTML headers and meta tags
├── footer.php            # Shared HTML footers and scripts
├── quiz.php              # Quiz-taking interface
├── review.php            # Post-quiz review and correct answers
├── manage_questions.php  # Admin interface for editing specific questions
├── logout.php            # Session termination script
└── /uploads              # Directory for user profile pictures
