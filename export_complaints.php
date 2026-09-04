<?php
include 'db_connect.php';

// Check if user confirmed export
if (isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=complaints_report_' . date("Y-m-d") . '.csv');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Complaint ID', 'Customer ID', 'Issue Description', 'Status', 'Date Reported', 'Date Resolved', 'Remarks', 'Handled By']);

    $query = "SELECT complaint_id, customer_id, issue_description, status, date_reported, date_resolved, remarks, handled_by 
              FROM complaints ORDER BY date_reported DESC";
    $result = mysqli_query($conn, $query);

    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            fputcsv($output, $row);
        }
    }

    fclose($output);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Confirm Export - Complaints</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
  <style>
    body {
      background-color: #F1F6FB;
      font-family: 'Nunito', sans-serif;
      color: #333;
      display: flex;
      align-items: center;
      justify-content: center;
      height: 100vh;
    }
    .confirm-card {
      background: #fff;
      border-radius: 10px;
      box-shadow: 0 6px 18px rgba(0,0,0,0.1);
      padding: 2rem;
      max-width: 500px;
      text-align: center;
    }
    .btn-blue {
      background-color: #00537A;
      color: white;
      border: none;
    }
    .btn-blue:hover {
      background-color: #0076A8;
      color: white;
    }
    .btn-yellow {
      background-color: #FFD35B;
      color: #000;
      border: none;
    }
    .btn-yellow:hover {
      background-color: #ffcd3a;
      color: #000;
    }
  </style>
</head>
<body>
  <div class="confirm-card">
    <i class="fa-solid fa-file-export fa-3x mb-3 text-warning"></i>
    <h4 class="fw-bold mb-2">Export Complaints Data</h4>
    <p class="text-muted mb-4">
      Are you sure you want to export all complaint records as a CSV file?<br>
      This will include all fields such as issue description, status, and handler.
    </p>
    <div class="d-flex justify-content-center gap-2">
      <a href="export_complaints.php?confirm=yes" class="btn btn-blue px-4">
        <i class="fa-solid fa-check me-1"></i> Yes, Export
      </a>
      <a href="complaints.php" class="btn btn-yellow px-4">
        <i class="fa-solid fa-xmark me-1"></i> Cancel
      </a>
    </div>
  </div>
</body>
</html>