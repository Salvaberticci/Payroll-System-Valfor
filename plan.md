# Plan for Adding Delete Button to Payroll Concepts Table

## Overview
Add a delete button to the "Conceptos de Nómina Existentes" table in payroll_concepts.php that allows removing specific payroll concepts via AJAX with confirmation dialog.

## Steps
1. Create delete_payroll_concept.php endpoint for AJAX deletion
2. Modify payroll_concepts.php to add delete button in actions column
3. Add JavaScript confirmation dialog and AJAX deletion logic

## Implementation Details
- Use AJAX POST request to delete_payroll_concept.php
- Include confirmation dialog before deletion
- Update table dynamically without page reload
- Handle success/error responses appropriately
- Use only JavaScript, no frameworks

## Files to Modify
- public/delete_payroll_concept.php (new)
- public/payroll_concepts.php (add button and JS)