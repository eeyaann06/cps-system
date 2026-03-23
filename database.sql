-- =============================================================
--  CCS Profiling System — Database Setup
--
--  Import via phpMyAdmin : Import tab → choose this file → Go
--  Import via terminal   : mysql -u root -p < cps.sql
-- =============================================================

-- -------------------------------------------------------------
--  2. Tables
-- -------------------------------------------------------------

-- users : admin accounts for system login
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `full_name`  VARCHAR(150)  NOT NULL,
    `username`   VARCHAR(100)  NOT NULL,
    `password`   VARCHAR(255)  NOT NULL,
    `role`       VARCHAR(50)   NOT NULL DEFAULT 'admin',
    `created_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_username` (`username`)

) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;


-- students : enrolled student profiles
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `students` (
    `id`                INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `student_id`        VARCHAR(50)   NOT NULL,
    `first_name`        VARCHAR(100)  NOT NULL,
    `last_name`         VARCHAR(100)  NOT NULL,
    `email`             VARCHAR(150)      NULL,
    `phone`             VARCHAR(30)       NULL,
    `date_of_birth`     DATE              NULL,
    `gender`            VARCHAR(20)       NULL,
    `address`           TEXT              NULL,
    `year_level`        VARCHAR(50)       NULL,
    `course`            VARCHAR(150)      NULL,
    `section`           VARCHAR(50)       NULL,
    `enrollment_status` VARCHAR(50)   NOT NULL DEFAULT 'Active',
    `gpa`               DECIMAL(4, 2) NOT NULL DEFAULT 0.00,
    `emergency_contact` VARCHAR(150)      NULL,
    `emergency_phone`   VARCHAR(30)       NULL,
    `created_at`        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_students_student_id` (`student_id`)

) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;


-- faculty : teaching and administrative staff profiles
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `faculty` (
    `id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `faculty_id`      VARCHAR(50)   NOT NULL,
    `first_name`      VARCHAR(100)  NOT NULL,
    `last_name`       VARCHAR(100)  NOT NULL,
    `email`           VARCHAR(150)      NULL,
    `phone`           VARCHAR(30)       NULL,
    `date_of_birth`   DATE              NULL,
    `gender`          VARCHAR(20)       NULL,
    `address`         TEXT              NULL,
    `department`      VARCHAR(150)      NULL,
    `position`        VARCHAR(100)      NULL,
    `specialization`  VARCHAR(150)      NULL,
    `employment_type` VARCHAR(50)   NOT NULL DEFAULT 'Full-Time',
    `hire_date`       DATE              NULL,
    `bio`             TEXT              NULL,
    `created_at`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_faculty_faculty_id` (`faculty_id`)

) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;


-- events : school events, ceremonies, and activities
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `events` (
    `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `title`            VARCHAR(255)  NOT NULL,
    `description`      TEXT              NULL,
    `event_date`       DATE          NOT NULL,
    `event_time`       TIME              NULL,
    `end_time`         TIME              NULL,
    `location`         VARCHAR(255)      NULL,
    `category`         VARCHAR(100)  NOT NULL DEFAULT 'General',
    `status`           VARCHAR(50)   NOT NULL DEFAULT 'Upcoming',
    `organizer`        VARCHAR(150)      NULL,
    `max_participants` INT UNSIGNED       NULL,
    `created_at`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`)

) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;


-- schedules : class schedule entries
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `schedules` (
    `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `subject_code` VARCHAR(50)   NOT NULL,
    `subject_name` VARCHAR(255)  NOT NULL,
    `faculty_id`   INT UNSIGNED      NULL,
    `day_of_week`  VARCHAR(20)   NOT NULL,
    `start_time`   TIME          NOT NULL,
    `end_time`     TIME          NOT NULL,
    `room`         VARCHAR(100)      NULL,
    `course`       VARCHAR(150)      NULL,
    `year_level`   VARCHAR(50)       NULL,
    `section`      VARCHAR(50)       NULL,
    `semester`     VARCHAR(50)       NULL,
    `school_year`  VARCHAR(20)       NULL,

    PRIMARY KEY (`id`)

) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;


-- college_research : research papers and theses repository
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `college_research` (
    `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `title`          VARCHAR(500)  NOT NULL,
    `author`         VARCHAR(255)  NOT NULL,
    `co_authors`     TEXT              NULL,
    `abstract`       TEXT          NOT NULL,
    `keywords`       TEXT              NULL,
    `research_type`  VARCHAR(100)  NOT NULL DEFAULT 'Thesis',
    `department`     VARCHAR(150)      NULL,
    `year_published` YEAR              NULL,
    `status`         VARCHAR(50)   NOT NULL DEFAULT 'Completed',
    `adviser`        VARCHAR(255)      NULL,
    `created_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`)

) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;


-- syllabus : subject syllabi with objectives and grading
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `syllabus` (
    `id`             INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    `subject_code`   VARCHAR(50)       NOT NULL,
    `subject_name`   VARCHAR(255)      NOT NULL,
    `department`     VARCHAR(150)          NULL,
    `course`         VARCHAR(150)          NULL,
    `year_level`     VARCHAR(50)           NULL,
    `semester`       VARCHAR(50)           NULL,
    `units`          TINYINT UNSIGNED      NULL,
    `description`    TEXT                  NULL,
    `objectives`     TEXT                  NULL,
    `grading_system` TEXT                  NULL,
    `faculty_id`     INT UNSIGNED          NULL,
    `created_at`     DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`)

) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;


-- curriculum : program curricula and total units
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `curriculum` (
    `id`              INT UNSIGNED       NOT NULL AUTO_INCREMENT,
    `curriculum_name` VARCHAR(255)       NOT NULL,
    `course`          VARCHAR(150)       NOT NULL,
    `department`      VARCHAR(150)           NULL,
    `effective_year`  VARCHAR(10)            NULL,
    `description`     TEXT                   NULL,
    `total_units`     SMALLINT UNSIGNED      NULL,
    `status`          VARCHAR(50)        NOT NULL DEFAULT 'Active',
    `created_at`      DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`)

) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;


-- lessons : individual lesson plans per subject
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `lessons` (
    `id`           INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    `title`        VARCHAR(255)      NOT NULL,
    `subject_code` VARCHAR(50)           NULL,
    `topic`        VARCHAR(255)          NULL,
    `content`      TEXT                  NULL,
    `objectives`   TEXT                  NULL,
    `materials`    TEXT                  NULL,
    `duration`     INT UNSIGNED          NULL COMMENT 'Duration in minutes',
    `lesson_type`  VARCHAR(100)      NOT NULL DEFAULT 'Lecture',
    `week_number`  TINYINT UNSIGNED      NULL,
    `faculty_id`   INT UNSIGNED          NULL,
    `created_at`   DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`)

) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;


-- =============================================================
--  Sample data (optional)
-- =============================================================

-- Students
INSERT INTO `students`
    (`student_id`, `first_name`, `last_name`, `email`, `phone`, `date_of_birth`,
     `gender`, `address`, `year_level`, `course`, `section`,
     `enrollment_status`, `gpa`, `emergency_contact`, `emergency_phone`)
VALUES
    ('STU-2024-001', 'Maria',  'Santos',  'maria.santos@edu.ph',  '09171234567', '2004-03-15', 'Female', '123 Rizal St, Laguna',       '3rd Year', 'BS Computer Science',     'CS3A',  'Active', 1.75, 'Juan Santos',   '09170000001'),
    ('STU-2024-002', 'Jose',   'Reyes',   'jose.reyes@edu.ph',    '09281234567', '2003-07-22', 'Male',   '456 Mabini Ave, Cavite',     '4th Year', 'BS Information Technology','IT4B',  'Active', 1.50, 'Rosa Reyes',    '09280000002'),
    ('STU-2024-003', 'Ana',    'Cruz',    'ana.cruz@edu.ph',      '09391234567', '2005-01-10', 'Female', '789 Bonifacio Rd, Batangas', '2nd Year', 'BS Education',            'ED2C',  'Active', 2.00, 'Pedro Cruz',    '09390000003'),
    ('STU-2024-004', 'Pedro',  'Garcia',  'pedro.garcia@edu.ph',  '09501234567', '2004-11-05', 'Male',   '321 Luna St, Quezon',        '3rd Year', 'BS Business Admin',       'BA3A',  'Active', 1.85, 'Elena Garcia',  '09500000004'),
    ('STU-2024-005', 'Liza',   'Mendoza', 'liza.mendoza@edu.ph',  '09611234567', '2003-09-18', 'Female', '654 Del Pilar, Laguna',      '4th Year', 'BS Nursing',              'NUR4A', 'Active', 1.25, 'Mario Mendoza', '09610000005');


-- Faculty
INSERT INTO `faculty`
    (`faculty_id`, `first_name`, `last_name`, `email`, `phone`, `date_of_birth`,
     `gender`, `address`, `department`, `position`, `specialization`,
     `employment_type`, `hire_date`, `bio`)
VALUES
    ('FAC-001', 'Ramon', 'Villanueva', 'ramon.villanueva@edu.ph', '09171111111', '1975-06-20', 'Male',   'Makati City',   'College of Computing', 'Dean / Professor',    'Artificial Intelligence',  'Full-Time', '2010-06-01', 'Expert in AI and Machine Learning with 15+ years in academia.'),
    ('FAC-002', 'Elena', 'Flores',     'elena.flores@edu.ph',     '09172222222', '1980-03-14', 'Female', 'Pasig City',    'College of Education', 'Associate Professor', 'Curriculum Development',   'Full-Time', '2015-08-15', 'Specialist in curriculum design and educational technology.'),
    ('FAC-003', 'Marco', 'Dela Cruz',  'marco.delacruz@edu.ph',   '09173333333', '1978-11-30', 'Male',   'Quezon City',   'College of Business',  'Professor',           'Management & Finance',     'Full-Time', '2008-01-10', 'Business management expert with extensive industry experience.'),
    ('FAC-004', 'Sarah', 'Aquino',     'sarah.aquino@edu.ph',     '09174444444', '1985-07-09', 'Female', 'Caloocan City', 'College of Nursing',   'Assistant Professor', 'Medical Surgical Nursing', 'Full-Time', '2018-06-01', 'Registered nurse and educator with clinical and teaching experience.');


-- Events
INSERT INTO `events`
    (`title`, `description`, `event_date`, `event_time`, `end_time`,
     `location`, `category`, `status`, `organizer`, `max_participants`)
VALUES
    ('Enrollment Period - 2nd Semester', 'Official enrollment for the second semester AY 2024-2025',        '2025-01-06', '08:00:00', '17:00:00', 'Registrar Office', 'Academic',      'Upcoming', 'Registrar Office', 500),
    ('Foundation Day Celebration',       'Annual foundation day with cultural presentations and activities', '2025-02-14', '09:00:00', '18:00:00', 'Main Gymnasium',   'Special Event', 'Upcoming', 'Student Affairs',  1000),
    ('Research Colloquium 2025',         'Annual research presentation and awarding ceremony',               '2025-03-20', '08:00:00', '17:00:00', 'Audio Visual Room','Academic',      'Upcoming', 'Research Office',  200),
    ('Intramurals 2025',                 'Annual inter-department sports competition',                       '2025-04-07', '07:00:00', '18:00:00', 'Campus Grounds',   'Sports',        'Upcoming', 'PE Department',    800),
    ('Graduation Ceremony',              'Commencement exercises for AY 2024-2025',                          '2025-05-25', '08:00:00', '12:00:00', 'Main Gymnasium',   'Academic',      'Upcoming', 'Academic Affairs', 1500);


-- Schedules
INSERT INTO `schedules`
    (`subject_code`, `subject_name`, `faculty_id`, `day_of_week`,
     `start_time`, `end_time`, `room`, `course`, `year_level`,
     `section`, `semester`, `school_year`)
VALUES
    ('CS301',  'Data Structures & Algorithms', 1, 'Monday',    '07:30:00', '09:00:00', 'Room 301',    'BS Computer Science',      '3rd Year', 'CS3A',  '1st Semester', '2024-2025'),
    ('CS302',  'Operating Systems',            1, 'Tuesday',   '09:00:00', '10:30:00', 'Lab 201',     'BS Computer Science',      '3rd Year', 'CS3A',  '1st Semester', '2024-2025'),
    ('IT401',  'Web Development',              2, 'Wednesday', '10:30:00', '12:00:00', 'Lab 301',     'BS Information Technology','4th Year', 'IT4B',  '1st Semester', '2024-2025'),
    ('ED201',  'Curriculum Theory',            2, 'Thursday',  '13:00:00', '14:30:00', 'Room 105',    'BS Education',             '2nd Year', 'ED2C',  '1st Semester', '2024-2025'),
    ('NUR401', 'Medical Surgical Nursing',     4, 'Friday',    '07:00:00', '10:00:00', 'Clinical Lab','BS Nursing',               '4th Year', 'NUR4A', '1st Semester', '2024-2025');


-- College Research
INSERT INTO `college_research`
    (`title`, `author`, `adviser`, `abstract`, `keywords`,
     `research_type`, `department`, `year_published`, `status`)
VALUES
    ('Predictive Analytics for Student Performance Using Machine Learning',
     'Maria Santos', 'Dr. Ramon Villanueva',
     'This study explores machine learning algorithms to predict student academic performance based on historical data, attendance, and behavioral patterns.',
     'machine learning, academic performance, predictive analytics',
     'Thesis', 'College of Computing', 2024, 'Completed'),

    ('Impact of Blended Learning on Student Engagement Post-Pandemic',
     'Ana Cruz', 'Prof. Elena Flores',
     'An analysis of blended learning effectiveness in higher education during and after the COVID-19 pandemic.',
     'blended learning, student engagement, post-pandemic',
     'Thesis', 'College of Education', 2024, 'Completed'),

    ('Financial Literacy Among College Students in Calabarzon',
     'Pedro Garcia', 'Dr. Marco Dela Cruz',
     'A descriptive study on the financial literacy levels and investment awareness of college students in the Calabarzon region.',
     'financial literacy, college students, Calabarzon',
     'Research Paper', 'College of Business', 2023, 'Completed');


-- Syllabus
INSERT INTO `syllabus`
    (`subject_code`, `subject_name`, `department`, `course`, `year_level`,
     `semester`, `units`, `description`, `objectives`, `grading_system`, `faculty_id`)
VALUES
    ('CS301', 'Data Structures & Algorithms', 'College of Computing', 'BS Computer Science',      '3rd Year', '1st Semester', 3,
     'Study of fundamental data structures and algorithms for efficient problem solving.',
     '1. Implement basic data structures\n2. Analyze algorithm complexity\n3. Apply sorting and searching algorithms',
     'Midterm 40%, Finals 40%, Activities 20%', 1),

    ('IT401', 'Web Development',              'College of Computing', 'BS Information Technology','4th Year', '1st Semester', 3,
     'Comprehensive study of modern web development technologies and frameworks.',
     '1. Build responsive web apps\n2. Implement front-end and back-end\n3. Deploy applications',
     'Midterm 35%, Finals 35%, Projects 30%', 2),

    ('ED201', 'Curriculum Theory',            'College of Education', 'BS Education',             '2nd Year', '1st Semester', 3,
     'Theoretical foundations of curriculum development and design for educators.',
     '1. Understand curriculum models\n2. Design learning objectives\n3. Evaluate curriculum',
     'Midterm 40%, Finals 40%, Portfolio 20%', 2);


-- Curriculum
INSERT INTO `curriculum`
    (`curriculum_name`, `course`, `department`, `effective_year`, `description`, `total_units`, `status`)
VALUES
    ('BS Computer Science Curriculum 2022',      'BS Computer Science',      'College of Computing', '2022', 'A comprehensive 4-year program covering computer science fundamentals, software engineering, AI, and networking.',  162, 'Active'),
    ('BS Information Technology Curriculum 2022','BS Information Technology','College of Computing', '2022', 'A 4-year program focused on IT infrastructure, web development, database management, and systems analysis.',       154, 'Active'),
    ('BS Education Curriculum 2022',             'BS Education',             'College of Education', '2022', 'A 4-year teacher education program with specializations in elementary and secondary education.',                  148, 'Active');


-- Lessons
INSERT INTO `lessons`
    (`title`, `subject_code`, `topic`, `content`, `objectives`,
     `materials`, `duration`, `lesson_type`, `week_number`, `faculty_id`)
VALUES
    ('Introduction to Arrays and Linked Lists',
     'CS301', 'Arrays, Linked Lists, Memory Allocation',
     'This lesson covers the fundamental concept of arrays and linked lists as primary data structures.',
     '1. Define arrays and linked lists\n2. Compare time complexities\n3. Implement basic operations',
     'Whiteboard, IDE (VS Code), handouts', 90, 'Lecture', 1, 1),

    ('HTML5 & CSS3 Fundamentals',
     'IT401', 'HTML5, CSS3, Responsive Design',
     'Covers the building blocks of modern web pages using semantic HTML5 elements and CSS3 styling.',
     '1. Write semantic HTML5\n2. Style with CSS3\n3. Create responsive layouts',
     'Laptop, Browser, Code editor', 120, 'Laboratory', 1, 2),

    ("Tyler's Curriculum Model",
     'ED201', 'Curriculum Rationale, Objectives',
     'An in-depth study of Ralph Tyler\'s curriculum model and the four fundamental questions in curriculum development.',
     '1. Explain Tyler\'s four questions\n2. Apply the model to lesson planning',
     'Textbook, Presentation slides', 60, 'Discussion', 1, 2);
-- 
