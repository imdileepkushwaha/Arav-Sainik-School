<?php
require_once 'includes/db_connect.php';

try {
    // Drop existing table if exists
    $pdo->exec("DROP TABLE IF EXISTS `students`");

    // Create the new table
    $createTable = "
        CREATE TABLE `students` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `ad_no` varchar(20) NOT NULL,
            `name` varchar(100) NOT NULL,
            `roll` varchar(20) NOT NULL,
            `class` varchar(50) NOT NULL,
            `dob` varchar(20) NOT NULL,
            `gender` enum('Male','Female','Other') NOT NULL,
            `mobile` varchar(20) NOT NULL,
            `category` varchar(50) NOT NULL,
            `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
            `avatar_id` int(11) NOT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ";
    $pdo->exec($createTable);

    // Insert dummy data
    $dummyData = [
        ['AD52365', 'Kathryn Murphy', '12', 'Class 1 (A)', '05 May 2012', 'Male', '209.555.0104', 'General', 'Active', 1],
        ['AD52365', 'Floyd Miles', '1', 'Class 2 (B)', '05 May 2012', 'Female', '209.555.0104', 'Special', 'Inactive', 2],
        ['AD52367', 'Cody Fisher', '7', 'Class 3 (A)', '12 Feb 2013', 'Male', '207.445.9821', 'OBC', 'Active', 3],
        ['AD52368', 'Jane Cooper', '8', 'Class 4 (C)', '17 Mar 2014', 'Female', '204.658.4421', 'Special', 'Inactive', 4],
        ['AD52369', 'Esther Howard', '15', 'Class 5 (B)', '25 Jul 2013', 'Female', '209.875.9987', 'General', 'Active', 5],
        ['AD52370', 'Albert Flores', '3', 'Class 6 (A)', '08 Dec 2011', 'Male', '208.324.1110', 'OBC', 'Inactive', 6],
        ['AD52371', 'Jenny Wilson', '9', 'Class 7 (C)', '19 Sep 2010', 'Female', '206.211.4567', 'General', 'Active', 7],
        ['AD52367', 'Jane Cooper', '5', 'Class 3 (A)', '12 Jan 2013', 'Female', '202.444.0089', 'OBC', 'Active', 8],
        ['AD52368', 'Cameron Williamson', '23', 'Class 4 (C)', '08 Jul 2011', 'Male', '203.111.0456', 'SC', 'Inactive', 9],
        ['AD52369', 'Theresa Webb', '10', 'Class 5 (A)', '18 Nov 2010', 'Female', '205.777.0190', 'General', 'Active', 10],
    ];

    $stmt = $pdo->prepare("INSERT INTO `students` (`ad_no`, `name`, `roll`, `class`, `dob`, `gender`, `mobile`, `category`, `status`, `avatar_id`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    foreach ($dummyData as $row) {
        $stmt->execute($row);
    }

    echo "Students table created and seeded successfully!\n";

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>
