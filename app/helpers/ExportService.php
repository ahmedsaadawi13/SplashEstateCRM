<?php
// FILE: /app/helpers/ExportService.php

/**
 * SplashEstate CRM - Export Service
 * Handles data export to CSV and PDF formats
 */

class ExportService {

    /**
     * Export data to CSV
     * @param array $data Data to export
     * @param array $headers Column headers
     * @param string $filename Output filename
     * @return bool Success status
     */
    public function exportToCSV($data, $headers, $filename = 'export.csv') {
        // Set headers for file download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Open output stream
        $output = fopen('php://output', 'w');

        // Add UTF-8 BOM for Excel compatibility
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // Write headers
        fputcsv($output, $headers);

        // Write data rows
        foreach ($data as $row) {
            $csvRow = array();
            foreach ($headers as $key => $header) {
                $csvRow[] = isset($row[$key]) ? $row[$key] : '';
            }
            fputcsv($output, $csvRow);
        }

        fclose($output);
        exit;
    }

    /**
     * Export leads to CSV
     * @param array $leads Lead data
     * @param string $filename Filename
     */
    public function exportLeadsToCSV($leads, $filename = 'leads_export.csv') {
        $headers = array(
            'id' => 'ID',
            'first_name' => 'First Name',
            'last_name' => 'Last Name',
            'email' => 'Email',
            'phone' => 'Phone',
            'source' => 'Source',
            'status' => 'Status',
            'interest_type' => 'Interest Type',
            'budget_min' => 'Budget Min',
            'budget_max' => 'Budget Max',
            'assigned_to_name' => 'Assigned To',
            'created_at' => 'Created Date'
        );

        $this->exportToCSV($leads, $headers, $filename);
    }

    /**
     * Export clients to CSV
     * @param array $clients Client data
     * @param string $filename Filename
     */
    public function exportClientsToCSV($clients, $filename = 'clients_export.csv') {
        $headers = array(
            'id' => 'ID',
            'first_name' => 'First Name',
            'last_name' => 'Last Name',
            'email' => 'Email',
            'phone' => 'Phone',
            'city' => 'City',
            'state' => 'State',
            'client_type' => 'Type',
            'status' => 'Status',
            'created_at' => 'Created Date'
        );

        $this->exportToCSV($clients, $headers, $filename);
    }

    /**
     * Export properties to CSV
     * @param array $properties Property data
     * @param string $filename Filename
     */
    public function exportPropertiesToCSV($properties, $filename = 'properties_export.csv') {
        $headers = array(
            'id' => 'ID',
            'title' => 'Title',
            'property_type' => 'Type',
            'listing_type' => 'Listing Type',
            'price' => 'Price',
            'bedrooms' => 'Bedrooms',
            'bathrooms' => 'Bathrooms',
            'square_feet' => 'Square Feet',
            'address' => 'Address',
            'city' => 'City',
            'state' => 'State',
            'zip' => 'ZIP',
            'status' => 'Status',
            'created_at' => 'Created Date'
        );

        $this->exportToCSV($properties, $headers, $filename);
    }

    /**
     * Export deals to CSV
     * @param array $deals Deal data
     * @param string $filename Filename
     */
    public function exportDealsToCSV($deals, $filename = 'deals_export.csv') {
        $headers = array(
            'id' => 'ID',
            'title' => 'Title',
            'client_name' => 'Client',
            'property_title' => 'Property',
            'deal_value' => 'Value',
            'commission' => 'Commission',
            'stage' => 'Stage',
            'probability' => 'Probability %',
            'expected_close_date' => 'Expected Close',
            'agent_name' => 'Agent',
            'created_at' => 'Created Date'
        );

        $this->exportToCSV($deals, $headers, $filename);
    }

    /**
     * Export data to PDF (simplified HTML to PDF)
     * @param string $html HTML content
     * @param string $filename Output filename
     * @param string $orientation Page orientation (portrait/landscape)
     */
    public function exportToPDF($html, $filename = 'export.pdf', $orientation = 'portrait') {
        // This is a simplified PDF export using HTML rendering
        // In production, use libraries like TCPDF, FPDF, or Dompdf

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        // For now, we'll export as HTML that can be printed to PDF
        // This requires the user to use browser's "Print to PDF" feature
        // To implement true PDF generation, integrate a PDF library

        echo $this->generatePDFHTML($html, $orientation);
        exit;
    }

    /**
     * Generate print-friendly HTML for PDF conversion
     * @param string $content HTML content
     * @param string $orientation Page orientation
     * @return string HTML
     */
    private function generatePDFHTML($content, $orientation = 'portrait') {
        $css = ($orientation === 'landscape') ? '@page { size: landscape; }' : '@page { size: portrait; }';

        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Export</title>
    <style>
        ' . $css . '
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #2c3e50;
            color: white;
        }
        tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        h1 {
            color: #2c3e50;
            border-bottom: 3px solid #3498db;
            padding-bottom: 10px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #7f8c8d;
        }
        @media print {
            button { display: none; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>SplashEstate CRM - Export</h1>
        <p>Generated: ' . date('F j, Y g:i A') . '</p>
        <button onclick="window.print()">Print / Save as PDF</button>
    </div>

    ' . $content . '

    <div class="footer">
        <p>&copy; ' . date('Y') . ' SplashEstate CRM. All rights reserved.</p>
    </div>
</body>
</html>';
    }

    /**
     * Generate leads report HTML
     * @param array $leads Lead data
     * @return string HTML
     */
    public function generateLeadsReportHTML($leads) {
        $html = '<h2>Leads Report</h2>';
        $html .= '<p>Total Leads: ' . count($leads) . '</p>';
        $html .= '<table>';
        $html .= '<thead><tr>';
        $html .= '<th>Name</th><th>Email</th><th>Phone</th><th>Status</th><th>Interest</th><th>Budget</th><th>Date</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($leads as $lead) {
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($lead['first_name'] . ' ' . $lead['last_name']) . '</td>';
            $html .= '<td>' . htmlspecialchars($lead['email']) . '</td>';
            $html .= '<td>' . htmlspecialchars($lead['phone']) . '</td>';
            $html .= '<td>' . htmlspecialchars(ucfirst($lead['status'])) . '</td>';
            $html .= '<td>' . htmlspecialchars(ucfirst($lead['interest_type'])) . '</td>';
            $html .= '<td>$' . number_format($lead['budget_min'], 0) . ' - $' . number_format($lead['budget_max'], 0) . '</td>';
            $html .= '<td>' . date('M j, Y', strtotime($lead['created_at'])) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';

        return $html;
    }

    /**
     * Generate properties report HTML
     * @param array $properties Property data
     * @return string HTML
     */
    public function generatePropertiesReportHTML($properties) {
        $html = '<h2>Properties Report</h2>';
        $html .= '<p>Total Properties: ' . count($properties) . '</p>';
        $html .= '<table>';
        $html .= '<thead><tr>';
        $html .= '<th>Title</th><th>Type</th><th>Price</th><th>Beds</th><th>Baths</th><th>Location</th><th>Status</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($properties as $property) {
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($property['title']) . '</td>';
            $html .= '<td>' . htmlspecialchars(ucfirst($property['property_type'])) . '</td>';
            $html .= '<td>$' . number_format($property['price'], 2) . '</td>';
            $html .= '<td>' . $property['bedrooms'] . '</td>';
            $html .= '<td>' . $property['bathrooms'] . '</td>';
            $html .= '<td>' . htmlspecialchars($property['city'] . ', ' . $property['state']) . '</td>';
            $html .= '<td>' . htmlspecialchars(ucfirst($property['status'])) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';

        return $html;
    }
}
