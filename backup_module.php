<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}
include("header.php");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Backup Generator</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.js"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .card { border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .form-label { font-weight: 500; }
        .btn-lg { padding: 12px 20px; font-size: 15px; border-radius: 8px; }
    </style>
</head>
<body class="bg-light py-4">

<div class="container">
    <div class="card p-4">
        <h4 class="mb-3">Website Data Backup </h4>
        <p class="text-muted mb-4">Easily generate a backup of your website by choosing your filter options.</p>

        <form action="generate_backup.php" method="GET" class="row g-3 align-items-end">

            <div class="col-md-3">
                <label class="form-label">Filter Type</label>
                <select name="filter" id="filter" class="form-select" required>
                    <option value="">-- Select --</option>
                    <option value="month">Monthly</option>
                    <option value="year">Yearly</option>
                    <option value="batch">Year of Passing</option>
                    <option value="range">Date Range</option>
                    <option value="all">All Data</option>
                </select>
            </div>

            <div class="col-md-3 filter-month d-none">
                <label class="form-label">Month</label>
                <input type="month" name="month_filter" class="form-control">
            </div>

            <div class="col-md-3 filter-year d-none">
                <label class="form-label">Year</label>
                <input type="number" name="year_filter" class="form-control" placeholder="e.g., 2025">
            </div>

            <div class="col-md-3 filter-batch d-none">
                <label class="form-label">Batch Year</label>
                <input type="number" name="batch_filter" class="form-control" placeholder="e.g., 2023">
            </div>

            <div class="col-md-3 filter-range d-none">
                <label class="form-label">From Date</label>
                <input type="date" name="from_date" class="form-control">
            </div>

            <div class="col-md-3 filter-range d-none">
                <label class="form-label">To Date</label>
                <input type="date" name="to_date" class="form-control">
            </div>

            <div class="col-md-12">
                <button class="exportBtn" type="submit" >
                    <i class="bi bi-download"></i> Download Backup
                </button>
            </div>
        </form>
    </div>
</div>
<script>
document.getElementById('filter').addEventListener('change', function(){
    const filter = this.value;

    // Hide all filter sections
    document.querySelectorAll('.filter-month, .filter-year, .filter-batch, .filter-range')
        .forEach(el => el.classList.add('d-none'));

    // Clear and disable all filter input fields
    document.querySelectorAll('input').forEach(input => {
        input.required = false;
        input.value = '';
    });

    if (filter === 'month') {
        document.querySelector('.filter-month').classList.remove('d-none');
        document.querySelector('[name="month_filter"]').required = true;
    }

    if (filter === 'year') {
        document.querySelector('.filter-year').classList.remove('d-none');
        document.querySelector('[name="year_filter"]').required = true;
    }

    if (filter === 'batch') {
        document.querySelector('.filter-batch').classList.remove('d-none');
        document.querySelector('[name="batch_filter"]').required = true;
    }

    if (filter === 'range') {
        document.querySelectorAll('.filter-range').forEach(el => el.classList.remove('d-none'));
        document.querySelector('[name="from_date"]').required = true;
        document.querySelector('[name="to_date"]').required = true;
    }
});
</script>


</body>
</html>
