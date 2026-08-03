<?php
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="students_import_sample.csv"');
echo "name,roll,class,section,dob,gender,mobile,category\n";
echo "Rahul Sharma,01,Class 1,A,2015-06-15,Male,9876543210,General\n";
echo "Priya Patel,02,Class 2,B,2014-03-20,Female,9876543211,OBC\n";
exit;
