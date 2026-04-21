-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 21, 2026 at 03:09 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `thesis_archiving`
--

-- --------------------------------------------------------

--
-- Table structure for table `archive_table`
--

CREATE TABLE `archive_table` (
  `archive_id` int(11) NOT NULL,
  `thesis_id` int(11) NOT NULL,
  `archived_by` int(11) NOT NULL,
  `archive_date` datetime NOT NULL,
  `retention_period` int(11) DEFAULT 5,
  `archive_notes` text DEFAULT NULL,
  `access_level` varchar(20) DEFAULT 'public',
  `views_count` int(11) DEFAULT 0,
  `downloads_count` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `audit_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action_type` varchar(255) NOT NULL,
  `table_name` varchar(100) NOT NULL,
  `record_id` int(11) NOT NULL,
  `description` text NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`audit_id`, `user_id`, `action_type`, `table_name`, `record_id`, `description`, `ip_address`, `created_at`) VALUES
(1, 8, 'Admin accessed dashboard', 'user_table', 8, 'Admin Ivon Candilanza accessed the admin dashboard', NULL, '2026-03-25 08:55:03'),
(2, 8, 'Admin accessed dashboard', 'user_table', 8, 'Admin Ivon Candilanza accessed the admin dashboard', NULL, '2026-03-25 08:55:04');

-- --------------------------------------------------------

--
-- Table structure for table `certificates_table`
--

CREATE TABLE `certificates_table` (
  `certificate_id` int(11) NOT NULL,
  `thesis_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `certificate_file` varchar(255) NOT NULL,
  `generated_date` datetime NOT NULL,
  `downloaded_count` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `certificates_table`
--

INSERT INTO `certificates_table` (`certificate_id`, `thesis_id`, `student_id`, `certificate_file`, `generated_date`, `downloaded_count`) VALUES
(1, 6, 2, 'certificate_6_1773283503.html', '2026-03-12 10:45:03', 0),
(2, 7, 2, 'certificate_7_1773322049.html', '2026-03-12 21:27:29', 0);

-- --------------------------------------------------------

--
-- Table structure for table `department_coordinator`
--

CREATE TABLE `department_coordinator` (
  `coordinator_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `department_id` int(11) NOT NULL,
  `position` varchar(255) NOT NULL,
  `assigned_date` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `department_table`
--

CREATE TABLE `department_table` (
  `department_id` int(11) NOT NULL,
  `department_code` varchar(100) NOT NULL,
  `department_name` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `department_table`
--

INSERT INTO `department_table` (`department_id`, `department_code`, `department_name`, `created_at`) VALUES
(1, 'BSIT', 'BS Information Technology', '2026-04-20 02:21:23'),
(2, 'BSCRIM', 'BS Criminology', '2026-04-20 02:21:23'),
(3, 'BSHTM', 'BS Hospitality Management', '2026-04-20 02:21:23'),
(4, 'BSED', 'BS Education', '2026-04-20 02:21:23'),
(5, 'BSBA', 'BS Business Administration', '2026-04-20 02:21:23');

-- --------------------------------------------------------

--
-- Table structure for table `faculty_table`
--

CREATE TABLE `faculty_table` (
  `faculty_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `department_id` int(11) NOT NULL,
  `specialization` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `feedback_table`
--

CREATE TABLE `feedback_table` (
  `feedback_id` int(11) NOT NULL,
  `thesis_id` int(11) NOT NULL,
  `faculty_id` int(11) NOT NULL,
  `comments` text NOT NULL,
  `feedback_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `feedback_table`
--

INSERT INTO `feedback_table` (`feedback_id`, `thesis_id`, `faculty_id`, `comments`, `feedback_date`) VALUES
(5, 4, 5, 'sample', '2026-03-04 09:59:44'),
(6, 5, 5, 'wrong', '2026-03-04 10:52:26'),
(7, 5, 5, 'wrong', '2026-03-04 10:52:32'),
(8, 6, 5, 'okay', '2026-03-11 13:04:13'),
(9, 7, 5, 'try', '2026-03-12 13:27:29');

-- --------------------------------------------------------

--
-- Table structure for table `group_members`
--

CREATE TABLE `group_members` (
  `id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role` enum('project_manager','co_author') DEFAULT 'co_author',
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `thesis_id` int(11) DEFAULT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(4) DEFAULT 0,
  `type` varchar(50) DEFAULT 'info',
  `link` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `user_id`, `thesis_id`, `message`, `is_read`, `type`, `link`, `created_at`) VALUES
(1, 9, 10, '📢 Thesis ready for review: \"student research management and digital storage system\" from student Mylene Raganas. Faculty: catalina sellar has approved this thesis.', 0, 'faculty_forward', NULL, '2026-04-10 05:31:17'),
(2, 7, 10, '📋 Thesis ready for Dean approval: \"student research management and digital storage system\" from student Mylene Raganas. Forwarded by Coordinator: Mylene Sellar', 0, 'dean_forward', NULL, '2026-04-10 05:58:55'),
(3, 10, 10, '📋 Thesis ready for Dean approval: \"student research management and digital storage system\" from student Mylene Raganas. Forwarded by Coordinator: Mylene Sellar', 0, 'dean_forward', NULL, '2026-04-10 05:58:55'),
(4, 7, 10, '✅ Thesis \"student research management and digital storage system\" has been APPROVED by Dean Tyrone James', 1, 'dean_approved', NULL, '2026-04-10 07:00:42'),
(5, 5, 13, 'New thesis submission from Mylene Raganas: \"ai-powered academic document archiving and intelli...\"', 0, 'thesis_submission', '../faculty/reviewThesis.php?id=13', '2026-04-10 11:54:22'),
(6, 11, 13, 'New thesis submission from Mylene Raganas: \"ai-powered academic document archiving and intelli...\"', 0, 'thesis_submission', '../faculty/reviewThesis.php?id=13', '2026-04-10 11:54:22'),
(8, 5, 14, 'New thesis submission from Mylene Raganas: \"cloud-based academic repository system with intell...\"', 0, 'thesis_submission', '../faculty/reviewThesis.php?id=14', '2026-04-10 13:06:27'),
(9, 11, 14, 'New thesis submission from Mylene Raganas: \"cloud-based academic repository system with intell...\"', 0, 'thesis_submission', '../faculty/reviewThesis.php?id=14', '2026-04-10 13:06:27'),
(10, 2, 14, '📢 New thesis forwarded for review: \"cloud-based academic repository system with intelligent search and recommendation engine\" from student Mylene Raganas. Faculty: catalina sellar has approved this thesis.', 0, 'faculty_forward', '../coordinator/reviewThesis.php?id=14', '2026-04-10 13:06:57'),
(11, 9, 14, '📢 New thesis forwarded for review: \"cloud-based academic repository system with intelligent search and recommendation engine\" from student Mylene Raganas. Faculty: catalina sellar has approved this thesis.', 1, 'faculty_forward', '../coordinator/reviewThesis.php?id=14', '2026-04-10 13:06:57'),
(12, 2, 14, '✅ Good news! Your thesis \"cloud-based academic repository system with intelligent search and recommendation engine\" has been APPROVED by Dean Tyrone James. It has been forwarded to the Librarian for archiving.', 0, 'student_notif', NULL, '2026-04-10 13:34:39'),
(13, 6, 14, '📚 Thesis approved by Dean for archiving: \"cloud-based academic repository system with intelligent search and recommendation engine\" from student Ms.Camille Joyce Geocallo. Approved by Dean: Tyrone James', 1, 'dean_approved', '../librarian/archiveThesis.php?id=14', '2026-04-10 13:34:39'),
(14, 7, 14, '✅ You approved thesis \"cloud-based academic repository system with intelligent search and recommendation engine\" and forwarded to Librarian', 1, 'dean_action', NULL, '2026-04-10 13:34:39'),
(15, 2, 14, '📚 Your thesis \"cloud-based academic repository system with intelligent search and recommendation engine\" has been ARCHIVED by Librarian Joyce Camille', 0, 'student_archived', NULL, '2026-04-10 15:13:24'),
(16, 5, 15, 'New thesis submission from Mylene Raganas: \"Development of a Mobile-Based Campus Navigation Sy...\"', 0, 'thesis_submission', '../faculty/reviewThesis.php?id=15', '2026-04-11 12:38:53'),
(17, 11, 15, 'New thesis submission from Mylene Raganas: \"Development of a Mobile-Based Campus Navigation Sy...\"', 0, 'thesis_submission', '../faculty/reviewThesis.php?id=15', '2026-04-11 12:38:53'),
(21, 5, 16, 'New thesis submission from Tyrone James Dela Victoria: \"The Impact of Digital Marketing Strategies on Smal...\"', 1, 'thesis_submission', '../faculty/reviewThesis.php?id=16', '2026-04-20 02:59:07'),
(22, 11, 16, 'New thesis submission from Tyrone James Dela Victoria: \"The Impact of Digital Marketing Strategies on Smal...\"', 0, 'thesis_submission', '../faculty/reviewThesis.php?id=16', '2026-04-20 02:59:07'),
(23, 5, 17, 'New thesis submission from Tyrone James Dela Victoria: \"Design and Implementation of an AI-Powered Chatbot...\"', 1, 'thesis_submission', '../faculty/reviewThesis.php?id=17', '2026-04-20 03:12:23'),
(24, 11, 17, 'New thesis submission from Tyrone James Dela Victoria: \"Design and Implementation of an AI-Powered Chatbot...\"', 0, 'thesis_submission', '../faculty/reviewThesis.php?id=17', '2026-04-20 03:12:23'),
(25, 5, 18, 'New thesis submission from Ivon Candilanza: \"Cybersecurity Awareness Among University Students:...\"', 1, 'thesis_submission', '../faculty/reviewThesis.php?id=18', '2026-04-20 03:27:11'),
(26, 11, 18, 'New thesis submission from Ivon Candilanza: \"Cybersecurity Awareness Among University Students:...\"', 0, 'thesis_submission', '../faculty/reviewThesis.php?id=18', '2026-04-20 03:27:11'),
(27, 5, 20, 'New thesis submission from Ivon Candilanza: \"The Influence of Social Media Usage on Academic Pe...\"', 1, 'thesis_submission', '../faculty/reviewThesis.php?id=20', '2026-04-20 07:09:49'),
(28, 11, 20, 'New thesis submission from Ivon Candilanza: \"The Influence of Social Media Usage on Academic Pe...\"', 0, 'thesis_submission', '../faculty/reviewThesis.php?id=20', '2026-04-20 07:09:49'),
(29, 2, 20, '📢 New thesis forwarded for review: \"The Influence of Social Media Usage on Academic Performance Among Senior High School Students\" from student Ivon Candilanza. Faculty: catalina sellar has approved this thesis.', 0, 'faculty_forward', '../coordinator/reviewThesis.php?id=20', '2026-04-20 07:26:36'),
(30, 9, 20, '📢 New thesis forwarded for review: \"The Influence of Social Media Usage on Academic Performance Among Senior High School Students\" from student Ivon Candilanza. Faculty: catalina sellar has approved this thesis.', 1, 'faculty_forward', '../coordinator/reviewThesis.php?id=20', '2026-04-20 07:26:36'),
(31, 16, 20, '✅ Good news! Your thesis \"The Influence of Social Media Usage on Academic Performance Among Senior High School Students\" has been APPROVED by Dean Tyrone James', 0, 'student_approved', NULL, '2026-04-20 08:16:33'),
(32, 3, 20, '📚 Thesis approved by Dean for archiving: \"The Influence of Social Media Usage on Academic Performance Among Senior High School Students\" from student MR. BREGILDO. Approved by Dean: Tyrone James', 0, 'dean_approved', '../librarian/archiveThesis.php?id=20', '2026-04-20 08:16:33'),
(33, 6, 20, '📚 Thesis approved by Dean for archiving: \"The Influence of Social Media Usage on Academic Performance Among Senior High School Students\" from student MR. BREGILDO. Approved by Dean: Tyrone James', 1, 'dean_approved', '../librarian/archiveThesis.php?id=20', '2026-04-20 08:16:33'),
(34, 7, 20, '✅ You approved thesis \"The Influence of Social Media Usage on Academic Performance Among Senior High School Students\" and forwarded to Librarian', 1, 'dean_action', NULL, '2026-04-20 08:16:33');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `otp` varchar(6) NOT NULL,
  `expires_at` datetime NOT NULL,
  `is_used` tinyint(4) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`id`, `email`, `otp`, `expires_at`, `is_used`, `created_at`) VALUES
(9, 'mylenesellar13@gmail.com', '435202', '2026-04-02 16:47:47', 0, '2026-04-02 14:32:47');

-- --------------------------------------------------------

--
-- Table structure for table `role_table`
--

CREATE TABLE `role_table` (
  `role_id` int(11) NOT NULL,
  `role_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `thesis_groups`
--

CREATE TABLE `thesis_groups` (
  `id` int(11) NOT NULL,
  `group_name` varchar(255) NOT NULL,
  `project_manager_id` int(11) NOT NULL,
  `department` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `thesis_table`
--

CREATE TABLE `thesis_table` (
  `thesis_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `abstract` text NOT NULL,
  `keywords` varchar(255) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `year` varchar(4) DEFAULT NULL,
  `adviser` varchar(255) NOT NULL,
  `status` varchar(100) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `date_submitted` datetime NOT NULL DEFAULT current_timestamp(),
  `is_archived` tinyint(1) DEFAULT 0,
  `archived_date` datetime DEFAULT NULL,
  `archived_by` int(11) DEFAULT NULL,
  `retention_period` int(11) DEFAULT NULL COMMENT 'in years',
  `archive_notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `thesis_table`
--

INSERT INTO `thesis_table` (`thesis_id`, `student_id`, `title`, `abstract`, `keywords`, `department`, `year`, `adviser`, `status`, `file_path`, `date_submitted`, `is_archived`, `archived_date`, `archived_by`, `retention_period`, `archive_notes`) VALUES
(4, 0, 'trydfasdfasdfd dfregtrt hghrtyh  grtg', 'YJERYTJRTHNGHTYIHGTNHNGJNHHIOTE[TH JITJHGERJG RJGIRUGKJ\'Q[PURGT JHIRGH IRHTUWRTN RKTHGREGNGIOSDGJITW]0RT45TJI84TJVJIREOUT5TIJQERQIERHERVJ IJRIEURGJIURGJRGRWY', NULL, NULL, NULL, 'MR. BREGILDO', 'approved', 'uploads/manuscripts/1772351838_69a3f15e2d232_trydfasdfasdfd_dfregtrt_hghrtyh__grtg.pdf', '2026-03-01 08:57:18', 0, NULL, NULL, NULL, NULL),
(5, 0, 'trydfasdfasdfd dfregtrt hghrtyh  grtg', 'YJERYTJRTHNGHTYIHGTNHNGJNHHIOTE[TH JITJHGERJG RJGIRUGKJ\'Q[PURGT JHIRGH IRHTUWRTN RKTHGREGNGIOSDGJITW]0RT45TJI84TJVJIREOUT5TIJQERQIERHERVJ IJRIEURGJIURGJRGRWY', NULL, NULL, NULL, 'MR. BREGILDO', 'rejected', 'uploads/manuscripts/1772351971_69a3f1e343482_trydfasdfasdfd_dfregtrt_hghrtyh__grtg.pdf', '2026-03-01 08:59:31', 0, NULL, NULL, NULL, NULL),
(6, 0, 'archiving', 'bvhvbsufvbc v;sfva;odfvjfdbvjb;djvb;scvnb;bnmdfbjsfbhb bhfgldygf hggyugf hpiydgfdf fagfpf bvbadufh osndjdv oksyy kfjvsdogf fkgdafjg jhgfoushg jhgouseg njgoiad irhhg\'ifhg goufgjnj fngjfhs jngjsnfgjnfg', NULL, NULL, NULL, 'camille joyce geocallo', 'approved', 'uploads/manuscripts/1773064034_69aecf629c828_archiving.pdf', '2026-03-09 14:47:14', 0, NULL, NULL, NULL, NULL),
(7, 0, 'enrollment', 'enrollment is the process where students officially register in a school or educational institution for a specific academic term. during this process, students submit required documents, select or confirm their courses, and pay necessary fees. the school records the student’s information in the system to confirm their admission and allow them to attend classes. enrollment helps the institution organize student records, manage class schedules, and ensure that students are properly registered for their chosen program.', NULL, NULL, NULL, 'MR. BREGILDO', 'approved', 'uploads/manuscripts/1773321994_69b2bf0ae329f_enrollment.pdf', '2026-03-12 14:26:34', 0, NULL, NULL, NULL, NULL),
(8, 0, 'web-based thesis archiving system', 'this study aims to develop and evaluate a web-based thesis archiving system designed to improve the storage, organization, and accessibility of academic research within a private college. many institutions still rely on manual or paper-based archiving of theses, which makes searching, retrieving, and managing research documents difficult and time-consuming. the proposed system provides a centralized digital platform where students can submit their thesis, while administrators and faculty members can review, approve, and archive these documents efficiently.\r\n\r\nthe system includes features such as user management, thesis submission, approval workflow, digital archiving, and report generation. it also allows authorized users to easily search and retrieve archived theses, improving accessibility for academic reference and research purposes. the development of the system follows a systematic process that includes requirements analysis, system design, development, and testing.', 'web-based system, thesis archiving, digital repository, academic document management, information retrieval, research management system', 'IT', '2026', 'MR. BREGILDO', 'pending', 'uploads/manuscripts/1773401374_69b3f51e282ec_web_based_thesis_archiving_system.pdf', '2026-03-13 12:29:34', 0, NULL, NULL, NULL, NULL),
(9, 0, 'digital research repository and management system for academic institutions', 'this study focuses on the development of a digital research repository and management system designed to improve the storage, organization, and accessibility of academic research within an educational institution. many schools still rely on traditional or manual methods of managing student research outputs, which often leads to difficulties in searching, retrieving, and preserving important documents. the proposed system provides a centralized digital platform where research papers, theses, and other academic documents can be securely uploaded, stored, and accessed by authorized users such as administrators, faculty members, and students. the system includes features such as document categorization, search functionality, secure user authentication, and administrative management tools. through the implementation of this system, the institution can enhance research accessibility, ensure better document preservation, and support knowledge sharing among students and faculty members.', 'digital repository, research management system, academic documents, information system, research database', 'IT', '2026', 'MR. BREGILDO', 'pending', 'uploads/manuscripts/1773411824_69b41df0692c6_digital_research_repository_and_management_system_.pdf', '2026-03-13 15:23:44', 0, NULL, NULL, NULL, NULL),
(10, 0, 'student research management and digital storage system', 'this study focuses on the development of a digital research repository and management system designed to improve the storage, organization, and accessibility of academic research within an educational institution. many schools still rely on traditional or manual methods of managing student research outputs, which often leads to difficulties in searching, retrieving, and preserving important documents. the proposed system provides a centralized digital platform where research papers, theses, and other academic documents can be securely uploaded, stored, and accessed by authorized users such as administrators, faculty members, and students. the system includes features such as document categorization, search functionality, secure user authentication, and administrative management tools. through the implementation of this system, the institution can enhance research accessibility, ensure better document preservation, and support knowledge sharing among students and faculty members.', 'digital repository, research management system, academic documents, information system, research database', 'IT', '2025', 'Ms.Camille Joyce Geocallo', 'approved', 'uploads/manuscripts/1773412463_69b4206f36811_student_research_management_and_digital_storage_sy.pdf', '2026-03-13 15:34:23', 0, NULL, NULL, NULL, NULL),
(12, 4, 'Smart Academic Document Management and Retrieval System', 'This study aims to design and develop a smart academic document management and retrieval system that will improve the storage, organization, and accessibility of academic documents such as theses, research papers, and institutional records. the system provides a centralized digital repository where users can securely upload, manage, and retrieve files using advanced search features and metadata indexing. it also includes user access control to ensure data security and proper authorization among administrators, faculty, and students. the proposed system addresses common issues such as document loss, slow manual retrieval, and lack of organization in traditional filing systems. by implementing a digital solution, the system enhances efficiency, accuracy, and convenience in handling academic documents. furthermore, it supports long-term preservation and easy sharing of knowledge within the institution. the study demonstrates how technology can improve academic workflows and contribute to better information management practices.', 'document management, digital archiving, academic system, information retrieval, metadata indexing, file management, data security', 'CS', '2027', 'MR. BREGILDO', 'pending', 'uploads/manuscripts/1775819879_69d8dc6780425_Smart_Academic_Document_Management_and_Retrieval_S.pdf', '2026-04-10 19:17:59', 0, NULL, NULL, NULL, NULL),
(13, 4, 'ai-powered academic document archiving and intelligent retrieval system', 'this study focuses on the development of an ai-powered academic document archiving and intelligent retrieval system designed to enhance the management of academic files such as theses, dissertations, and research papers. the system utilizes artificial intelligence techniques, including natural language processing and metadata-based indexing, to enable faster and more accurate document searching and classification. it provides a centralized, web-based platform where users can upload, organize, and retrieve documents efficiently. the system also integrates role-based access control to ensure data privacy and security among administrators, faculty members, and students. unlike traditional manual filing systems, the proposed solution minimizes document loss, reduces retrieval time, and improves overall productivity. additionally, the intelligent search feature allows users to find relevant documents using keywords, phrases, or related topics. the implementation of this system promotes digital transformation in academic institutions and supports effective knowledge sharing and long-term data preservation.', 'artificial intelligence, document archiving, intelligent retrieval, natural language processing, academic system, metadata indexing, data security, web-based system', 'IT', '2026', 'MR. BREGILDO', 'archived', 'uploads/manuscripts/1775822062_69d8e4ee72314_ai_powered_academic_document_archiving_and_intelli.pdf', '2026-04-10 19:54:22', 0, '2026-04-20 13:38:02', 0, NULL, NULL),
(14, 4, 'cloud-based academic repository system with intelligent search and recommendation engine', 'this study aims to develop a cloud-based academic repository system integrated with an intelligent search and recommendation engine to improve the storage and accessibility of academic documents such as theses, dissertations, and research papers. the system allows users to upload and manage documents in a centralized cloud environment, ensuring scalability, data security, and remote accessibility. it incorporates advanced search functionality using keyword matching and metadata filtering to provide fast and accurate results. additionally, a recommendation engine suggests related documents based on user queries and document content, enhancing research efficiency and knowledge discovery. the system also implements role-based access control to regulate user permissions and protect sensitive data. by replacing traditional manual archiving methods, the proposed system reduces document loss, improves retrieval speed, and supports collaborative academic work. this innovation contributes to the digital transformation of academic institutions and promotes efficient information management.', 'cloud computing, academic repository, recommendation system, information retrieval, metadata, digital archiving, data security, web-based system', 'BUS', '2025', 'Ms.Camille Joyce Geocallo', 'archived', 'uploads/manuscripts/1775826387_69d8f5d300da1_cloud_based_academic_repository_system_with_intell.pdf', '2026-04-10 21:06:27', 0, '2026-04-10 23:13:24', NULL, NULL, NULL),
(15, 4, 'Development of a Mobile-Based Campus Navigation System Using QR Code Technology', 'Navigating large educational campuses can be challenging for new students and visitors. This study aimed to develop a mobile-based campus navigation system that utilizes QR code technology to provide real-time directions and location-based information. The system allows users to scan QR codes placed in strategic locations to access maps, building details, and route guidance. The development followed the Agile methodology, incorporating user feedback throughout the design process. Testing results showed that the system significantly improved navigation efficiency and user satisfaction compared to traditional signage. The study concludes that integrating QR code technology in campus navigation is a cost-effective and user-friendly solution.', 'Mobile application, QR code, campus navigation, user experience, Agile development', 'ENG', '2026', 'Mylene Sellar', 'pending', 'uploads/manuscripts/1775911133_69da40dd69730_Development_of_a_Mobile_Based_Campus_Navigation_Sy.pdf', '2026-04-11 20:38:53', 0, NULL, NULL, NULL, NULL),
(16, 14, 'The Impact of Digital Marketing Strategies on Small Business Growth in the Philippines', 'This study examines the impact of digital marketing strategies on the growth of small businesses in the Philippines. With the rapid expansion of internet usage and social media platforms, small enterprises are increasingly adopting digital tools to enhance their market reach and competitiveness. The research focuses on commonly used strategies such as social media marketing, search engine optimization (SEO), email marketing, and online advertising. Using a quantitative research design, data were collected from small business owners through structured surveys to assess the effectiveness of these strategies in terms of customer engagement, sales growth, and brand visibility.\r\n\r\nThe findings reveal that digital marketing significantly contributes to business growth, particularly through social media platforms, which provide cost-effective and targeted marketing opportunities. Moreover, businesses that consistently implement digital strategies demonstrate higher customer retention and improved financial performance. However, challenges such as limited technical knowledge, budget constraints, and rapidly changing digital trends were also identified.\r\n\r\nThe study concludes that digital marketing is a crucial factor in the sustainability and expansion of small businesses in the Philippines. It recommends that business owners invest in digital skills development and adopt strategic marketing plans to maximize the benefits of digital platforms.', 'Digital Marketing, Small Business Growth, Social Media Marketing, Philippines, Online Advertising, Customer Engagement, SEO, E-commerce', 'BSHTM', '2026', 'Ms.Camille Joyce Geocallo', 'pending', 'uploads/manuscripts/1776653947_69e5967b8d795_The_Impact_of_Digital_Marketing_Strategies_on_Smal.pdf', '2026-04-20 10:59:07', 0, NULL, NULL, NULL, NULL),
(17, 14, 'Design and Implementation of an AI-Powered Chatbot for Student Services', 'This study focuses on the design and implementation of an AI-powered chatbot for student services aimed at improving accessibility, efficiency, and responsiveness in handling student inquiries. Educational institutions often face challenges in providing timely support due to the high volume of requests related to admissions, enrollment, schedules, and general academic information. To address this issue, the research proposes the development of a chatbot system capable of delivering instant, accurate, and user-friendly responses.\r\n\r\nThe study adopts a development-based research approach, utilizing natural language processing (NLP) techniques and machine learning algorithms to enable the chatbot to understand and respond to user queries effectively. The system is designed to be accessible through web and mobile platforms, ensuring convenience for students. Testing and evaluation were conducted using usability metrics such as response accuracy, user satisfaction, and system efficiency.\r\n\r\nResults indicate that the AI-powered chatbot significantly reduces response time and improves user satisfaction compared to traditional student service methods. The chatbot demonstrated a high level of accuracy in answering frequently asked questions and provided consistent support without human intervention. However, limitations were observed in handling complex or ambiguous queries, highlighting the need for continuous training and system improvement.\r\n\r\nThe study concludes that AI-powered chatbots can serve as a valuable tool in enhancing student services, streamlining communication, and supporting digital transformation in educational institutions.', 'AI Chatbot, Student Services, Natural Language Processing, Machine Learning, Automation, User Satisfaction, Educational Technology, System Development', 'BSIT', '2026', 'MR. BREGILDO', 'pending', 'uploads/manuscripts/1776654743_69e5999722085_Design_and_Implementation_of_an_AI_Powered_Chatbot.pdf', '2026-04-20 11:12:23', 0, NULL, NULL, NULL, NULL),
(20, 16, 'The Influence of Social Media Usage on Academic Performance Among Senior High School Students', 'This study explores the influence of social media usage on the academic performance of senior high school students. With the widespread use of platforms such as Facebook, Instagram, and TikTok, students spend a significant amount of time ონლაინ, which may affect their study habits and overall academic outcomes. The research aims to determine whether there is a significant relationship between the frequency and purpose of social media use and students’ academic performance.\r\n\r\nA quantitative research design was employed, using structured questionnaires distributed to senior high school students. The data collected included information on time spent on social media, types of platforms used, and students’ grade point averages (GPA). Statistical analysis was conducted to identify correlations between social media usage patterns and academic achievement.\r\n\r\nThe findings indicate that excessive use of social media for non-academic purposes is associated with lower academic performance, while moderate and academically-oriented use can have positive effects, such as improved collaboration and access to learning resources. The study also reveals that time management plays a crucial role in balancing social media use and academic responsibilities.\r\n\r\nThe study concludes that while social media can be a valuable educational tool, improper and excessive use may hinder academic success. It is recommended that students practice responsible usage, and that educators and parents guide students in developing effective time management and digital literacy skills.', 'Social Media, Academic Performance, Senior High School Students, Time Management, Digital Literacy, Online Behavior, Education Technology', 'BSIT', '2026', 'MR. BREGILDO', 'archived', 'uploads/manuscripts/1776668989_69e5d13dd1c1b_The_Influence_of_Social_Media_Usage_on_Academic_Pe.pdf', '2026-04-20 15:09:49', 0, '2026-04-20 13:06:29', 0, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_settings`
--

CREATE TABLE `user_settings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `reset_method` enum('email','sms') DEFAULT 'email',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_settings`
--

INSERT INTO `user_settings` (`id`, `user_id`, `reset_method`, `updated_at`) VALUES
(1, 4, 'sms', '2026-04-02 10:11:42');

-- --------------------------------------------------------

--
-- Table structure for table `user_table`
--

CREATE TABLE `user_table` (
  `user_id` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `role_id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(100) NOT NULL,
  `department` varchar(100) NOT NULL,
  `birth_date` varchar(100) NOT NULL,
  `address` varchar(100) NOT NULL,
  `contact_number` int(11) NOT NULL,
  `status` varchar(100) NOT NULL,
  `profile_picture` varchar(100) NOT NULL,
  `date_created` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_table`
--

INSERT INTO `user_table` (`user_id`, `student_id`, `role_id`, `first_name`, `last_name`, `email`, `username`, `password`, `department`, `birth_date`, `address`, `contact_number`, `status`, `profile_picture`, `date_created`, `updated_at`) VALUES
(1, NULL, 1, 'mylenee', 'raganas', 'raganas12@gmail.com', 'mylene13', '$2y$10$dr718ZjLNvJ8PV0Q1I2tNOlNyPdXyYKseoSrU2RkatjUQOSeGQ68q', 'BSIT', '2005-05-13', 'naga', 958473215, 'Active', 'default.png', '2026-02-17 11:49:33', '2026-02-17 12:13:25'),
(2, NULL, 6, 'Mylene', 'Raganas', 'raganas@gmail.com', 'raganas', '$2y$10$opyEbvyBTppUMYsadb0uXOH0j.i4bkBUNgfNerWK7cJBesTtqyfhq', 'BSIT', '2005-05-13', 'naga city', 985478548, 'Inactive', 'default.png', '2026-02-17 12:09:12', '2026-02-17 12:09:12'),
(3, NULL, 5, 'Camille Joyce', 'Geocallo', 'mylenesellar13@gmail.com', 'raganass', '$2y$10$yk76jFqEkaGzlcnXT2e2auvT399gu3cNaJ0sS74XIIMAc9ml7rXlW', 'HTM', '2005-05-12', 'San Fernando', 2147483647, 'Active', 'user_3_1771733497.jpg', '2026-02-17 12:57:57', '2026-02-22 12:11:37'),
(4, NULL, 2, 'Mylene', 'Raganas', 'mylene321@gmail.com', 'mylene321', '$2y$10$o2MZ49EX1sSVhpQPsOS/IeyjZ2F6JzgW2eDyqlvXJaBjwUE94/lqm', 'BSIT', '2005-05-13', 'Naga City', 2147483647, 'Active', 'user_4_1772109048.png', '2026-02-20 18:22:45', '2026-02-26 20:30:48'),
(5, NULL, 3, 'catalina', 'sellar', 'catalina30@gmail.com', 'faculty', '$2y$10$UjQwrV7qr1WsC16yYWWU/.GITEN09bwRqJmq6QFSH/MPXYTJu84Ai', 'BSIT', '2026-03-10', 'naga', 2147483647, 'Active', 'default.png', '2026-02-25 23:50:31', '2026-04-02 19:30:35'),
(6, NULL, 5, 'Joyce', 'Camille', 'camille@gmail.com', 'librarian', '$2y$10$k/onlfwCFaMLPGucLuhslePVx71LaCG9yKIsDVG5cROUdqxoc/bDS', 'BSBA', '1998-03-25', 'Bairan City of Naga', 2147483647, 'Active', 'default.png', '2026-03-24 17:18:35', '2026-03-24 17:18:35'),
(7, NULL, 4, 'Tyrone', 'James', 'james@gmail.com', 'dean', '$2y$10$nI6V7VQuPEVqgBl7m674Zeqngh7FDhXjSCxKzgqlF10CG2GwHsZ2y', '', '1995-10-19', 'Langtad, City of Naga', 2147483647, 'Active', 'default.png', '2026-03-25 14:17:48', '2026-03-25 14:17:48'),
(8, NULL, 1, 'Ivon', 'Candilanza', 'ivon@gmail.com', 'admin', '$2y$10$dJy1alj9W27vtFrt8Jo.C.f92sS5K4cH9Hav5zJiMI8c89RgxeNfC', '', '2009-06-23', 'San Fernando', 2147483647, 'Active', 'default.png', '2026-03-25 15:02:44', '2026-03-25 15:02:44'),
(9, NULL, 6, 'Mylene', 'Sellar', 'mylene@gmail.com', 'coordinator', '$2y$10$WKbauz7tLp45yshNBBDl../fUbbVU9SpFNlOawi1fen2zO5jcVFG2', '', '2000-10-19', 'Bairan City of Naga', 548796254, 'Active', 'default.png', '2026-03-25 17:33:26', '2026-03-25 17:33:26'),
(10, NULL, 4, 'Jorvin', 'Pengoc', 'pengoc@gmail.com', 'dean1', '$2y$10$v29jVaJkOgKBjIwdhLUEyOjSGOgPOKZR2aom1TzIg5Xddcf2/MfDe', '', '1998-10-16', 'Minglanilla', 2147483647, 'Active', 'default.png', '2026-03-26 20:43:59', '2026-03-26 20:43:59'),
(11, NULL, 3, 'Joyce', 'Geocallo', 'geocallocamillejoyce72@gmail.com', 'joycey', '$2y$10$QiwrYi5fJ5dJYBy4K5K1o.WzeRU/fQi.UL3EfPp7ymFB0qKk2X9re', '', '', '', 0, 'Active', '', '2026-04-01 16:58:30', '2026-04-01 16:58:30'),
(12, NULL, 2, 'Joyce', 'Camille', 'hohayhaha@gmail.com', 'student', '$2y$10$u.5z5zbTw8IGIxm.sI7Hv.z.z2xLNkNhcJXvYL7w8VmgJSaXViwLC', '', '', 'Langtad City of Naga', 2147483647, 'Active', '', '2026-04-15 21:49:04', '2026-04-15 21:49:04'),
(13, NULL, 2, 'Jorvin', 'Pengoc', 'pengocjorvin@gmail.com', 'jorvin', '$2y$10$XZf7x1nE2MH1OdVZVNK2xemmIGyVPJAg4e/KDhDbxMlJWIMtpaLMu', '', '2003-07-09', 'Minglanilla', 2147483647, '', '', '2026-04-20 10:27:26', '2026-04-20 10:27:26'),
(14, NULL, 2, 'Tyrone James', 'Dela Victoria', 'tyronedelavictoria2@gmail.com', 'tyronejames', '$2y$10$if0JMIMc85syxK05Avra0.9HZif6bD1Qg1mW2T7hu/D/.iHdK9sma', '', '2004-10-21', 'Sangat, San Fernando', 658421578, 'Active', '', '2026-04-20 10:35:23', '2026-04-20 10:35:23'),
(16, NULL, 2, 'Ivon', 'Candilanza', 'ivon11ki@gmail.com', 'ivon', '$2y$10$g4Sk8mjDtDQNk/qb/5X0ju5O/ZoSeqiOih2QgllgI6LyEzLZEGZ/.', '', '2004-10-25', 'San Fernando', 965847854, 'Active', '', '2026-04-20 10:42:24', '2026-04-20 10:42:24'),
(17, NULL, 2, 'Mylene', 'Villareal', 'raganasmylene@gmail.com', 'raganas13', '$2y$10$bthW3DXZuREeXoT4TWA6uubiL0AX7iezVf3LlfECqsf2rD8bFdor6', '', '2005-05-13', 'Langtad, City of Naga', 965830378, '', '', '2026-04-20 10:48:01', '2026-04-20 10:48:01'),
(18, NULL, 2, 'April', 'Villareal', 'aprilsellar@gmail.com', 'april', '$2y$10$zAAhviUvJDMK3GyM0fyMJeWMIie6CrDkNRJI2IL2HpQNBdCO7ZR4u', '', '2007-04-26', 'Langtad', 965004039, '', '', '2026-04-20 10:53:48', '2026-04-20 10:53:48');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `archive_table`
--
ALTER TABLE `archive_table`
  ADD PRIMARY KEY (`archive_id`),
  ADD KEY `thesis_id` (`thesis_id`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`audit_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `certificates_table`
--
ALTER TABLE `certificates_table`
  ADD PRIMARY KEY (`certificate_id`),
  ADD KEY `fk_certificates_thesis` (`thesis_id`),
  ADD KEY `fk_certificates_student` (`student_id`);

--
-- Indexes for table `department_coordinator`
--
ALTER TABLE `department_coordinator`
  ADD PRIMARY KEY (`coordinator_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `department_id` (`department_id`);

--
-- Indexes for table `department_table`
--
ALTER TABLE `department_table`
  ADD PRIMARY KEY (`department_id`);

--
-- Indexes for table `faculty_table`
--
ALTER TABLE `faculty_table`
  ADD PRIMARY KEY (`faculty_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `feedback_table`
--
ALTER TABLE `feedback_table`
  ADD PRIMARY KEY (`feedback_id`),
  ADD KEY `thesis_id` (`thesis_id`),
  ADD KEY `feedback_table_ibfk_2` (`faculty_id`);

--
-- Indexes for table `group_members`
--
ALTER TABLE `group_members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_member` (`group_id`,`user_id`),
  ADD KEY `idx_group` (`group_id`),
  ADD KEY `idx_user` (`user_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `thesis_id` (`thesis_id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `role_table`
--
ALTER TABLE `role_table`
  ADD PRIMARY KEY (`role_id`);

--
-- Indexes for table `thesis_groups`
--
ALTER TABLE `thesis_groups`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_project_manager` (`project_manager_id`);

--
-- Indexes for table `thesis_table`
--
ALTER TABLE `thesis_table`
  ADD PRIMARY KEY (`thesis_id`);

--
-- Indexes for table `user_settings`
--
ALTER TABLE `user_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `user_table`
--
ALTER TABLE `user_table`
  ADD PRIMARY KEY (`user_id`),
  ADD KEY `role_id` (`role_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `archive_table`
--
ALTER TABLE `archive_table`
  MODIFY `archive_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `audit_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=166;

--
-- AUTO_INCREMENT for table `certificates_table`
--
ALTER TABLE `certificates_table`
  MODIFY `certificate_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `department_coordinator`
--
ALTER TABLE `department_coordinator`
  MODIFY `coordinator_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `department_table`
--
ALTER TABLE `department_table`
  MODIFY `department_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `faculty_table`
--
ALTER TABLE `faculty_table`
  MODIFY `faculty_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `feedback_table`
--
ALTER TABLE `feedback_table`
  MODIFY `feedback_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `group_members`
--
ALTER TABLE `group_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `role_table`
--
ALTER TABLE `role_table`
  MODIFY `role_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `thesis_groups`
--
ALTER TABLE `thesis_groups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `thesis_table`
--
ALTER TABLE `thesis_table`
  MODIFY `thesis_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `user_settings`
--
ALTER TABLE `user_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `user_table`
--
ALTER TABLE `user_table`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `archive_table`
--
ALTER TABLE `archive_table`
  ADD CONSTRAINT `archive_table_ibfk_1` FOREIGN KEY (`thesis_id`) REFERENCES `thesis_table` (`thesis_id`) ON DELETE CASCADE;

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user_table` (`user_id`);

--
-- Constraints for table `certificates_table`
--
ALTER TABLE `certificates_table`
  ADD CONSTRAINT `fk_certificates_student` FOREIGN KEY (`student_id`) REFERENCES `user_table` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_certificates_thesis` FOREIGN KEY (`thesis_id`) REFERENCES `thesis_table` (`thesis_id`) ON DELETE CASCADE;

--
-- Constraints for table `department_coordinator`
--
ALTER TABLE `department_coordinator`
  ADD CONSTRAINT `department_coordinator_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user_table` (`user_id`),
  ADD CONSTRAINT `department_coordinator_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `department_table` (`department_id`);

--
-- Constraints for table `faculty_table`
--
ALTER TABLE `faculty_table`
  ADD CONSTRAINT `faculty_table_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user_table` (`user_id`);

--
-- Constraints for table `feedback_table`
--
ALTER TABLE `feedback_table`
  ADD CONSTRAINT `feedback_table_ibfk_1` FOREIGN KEY (`thesis_id`) REFERENCES `thesis_table` (`thesis_id`),
  ADD CONSTRAINT `feedback_table_ibfk_2` FOREIGN KEY (`faculty_id`) REFERENCES `user_table` (`user_id`);

--
-- Constraints for table `user_settings`
--
ALTER TABLE `user_settings`
  ADD CONSTRAINT `user_settings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user_table` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `user_table`
--
ALTER TABLE `user_table`
  ADD CONSTRAINT `user_table_ibfk_2` FOREIGN KEY (`role_id`) REFERENCES `user_table` (`user_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
