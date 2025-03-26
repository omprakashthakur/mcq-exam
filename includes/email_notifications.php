<?php

function send_exam_access_notification($email, $exam_title, $access_code, $start_date, $end_date = null) {
    $subject = "Exam Access Granted: $exam_title";
    
    $message = "Hello,\n\n";
    $message .= "You have been granted access to the exam: $exam_title\n\n";
    $message .= "Access Code: $access_code\n";
    $message .= "Start Date: " . date('Y-m-d H:i', strtotime($start_date)) . "\n";
    
    if ($end_date) {
        $message .= "End Date: " . date('Y-m-d H:i', strtotime($end_date)) . "\n";
    }
    
    $message .= "\nPlease log in to your account to take the exam.\n";
    $message .= "Note: This access code will only work during the specified time period.\n\n";
    $message .= "Good luck!\n";
    
    // Send email using PHP mail function
    $headers = "From: MCQ Exam System <noreply@mcqexam.com>\r\n";
    $headers .= "Reply-To: noreply@mcqexam.com\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    return mail($email, $subject, $message, $headers);
}

function send_exam_completion_notification($email, $exam_title, $score, $pass_percentage) {
    $subject = "Exam Completed: $exam_title";
    
    $message = "Hello,\n\n";
    $message .= "You have completed the exam: $exam_title\n\n";
    $message .= "Your Score: $score%\n";
    $message .= "Pass Percentage: $pass_percentage%\n\n";
    
    if ($score >= $pass_percentage) {
        $message .= "Congratulations! You have passed the exam.\n";
    } else {
        $message .= "Unfortunately, you did not meet the passing score for this exam.\n";
    }
    
    $message .= "\nYou can view your detailed results by logging into your account.\n";
    
    $headers = "From: MCQ Exam System <noreply@mcqexam.com>\r\n";
    $headers .= "Reply-To: noreply@mcqexam.com\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    return mail($email, $subject, $message, $headers);
}

function send_exam_approval_email($email, $exam_title, $access_code, $access_url, $expiry_date, $duration_minutes, $remarks = '') {
    $subject = "Exam Request Approved: $exam_title";
    
    $message = "Hello,\n\n";
    $message .= "Your request to take the exam '$exam_title' has been approved.\n\n";
    $message .= "Access Details:\n";
    $message .= "- Access Code: $access_code\n";
    $message .= "- Duration: $duration_minutes minutes\n";
    $message .= "- Expires: " . date('Y-m-d H:i', strtotime($expiry_date)) . "\n";
    
    if ($remarks) {
        $message .= "\nRemarks from administrator:\n$remarks\n";
    }
    
    $message .= "\nTo start the exam, please login to your account and use the provided access code.\n";
    $message .= "Note: The exam must be completed in one session and within the time limit.\n\n";
    $message .= "Good luck!\n";
    
    $headers = "From: MCQ Exam System <noreply@mcqexam.com>\r\n";
    $headers .= "Reply-To: noreply@mcqexam.com\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    return mail($email, $subject, $message, $headers);
}

function send_exam_rejection_email($email, $exam_title, $remarks = '') {
    $subject = "Exam Request Rejected: $exam_title";
    
    $message = "Hello,\n\n";
    $message .= "Your request to take the exam '$exam_title' has been rejected.\n\n";
    
    if ($remarks) {
        $message .= "Reason for rejection:\n$remarks\n\n";
    }
    
    $message .= "If you have any questions, please contact the administrator.\n";
    
    $headers = "From: MCQ Exam System <noreply@mcqexam.com>\r\n";
    $headers .= "Reply-To: noreply@mcqexam.com\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    return mail($email, $subject, $message, $headers);
}