/**
 * Has-Many Table Validation
 * Check for hidden required fields before form submission
 */
(function($) {
    'use strict';

    /**
     * Check if an element is hidden (display:none or visibility:hidden or parent hidden)
     */
    function isElementHidden($element) {
        if (!$element || $element.length === 0) {
            return false;
        }
        
        // Check if element or any parent has display:none or visibility:hidden
        return $element.is(':hidden') || 
               $element.css('visibility') === 'hidden' ||
               $element.parents().is(':hidden');
    }

    /**
     * Get field label from table header
     */
    function getFieldLabel($field) {
        var $row = $field.closest('tr');
        var $table = $field.closest('table.has-many-table');
        var cellIndex = $field.closest('td').index();
        
        // Get header cell
        var $headerCell = $table.find('thead tr th').eq(cellIndex);
        
        if ($headerCell.length) {
            // Get text and remove help icon if exists
            var label = $headerCell.clone()
                .find('i.fa-info-circle').remove().end()
                .text().trim();
            return label;
        }
        
        // Fallback: try to get from label or placeholder
        var $label = $field.closest('.form-group').find('label');
        if ($label.length) {
            return $label.text().trim();
        }
        
        return $field.attr('placeholder') || $field.attr('name') || 'Unknown field';
    }

    /**
     * Get table name
     */
    function getTableName($table) {
        // Try to get from header
        var $header = $table.closest('.has-many-table-div').find('.field-header');
        if ($header.length) {
            return $header.text().trim();
        }
        
        // Fallback to table class name
        var classes = $table.attr('class').split(' ');
        for (var i = 0; i < classes.length; i++) {
            if (classes[i].indexOf('has-many-table-') === 0 && classes[i].indexOf('-table') > 0) {
                var tableName = classes[i].replace('has-many-table-', '').replace('-table', '');
                return tableName.replace(/_/g, ' ').replace(/\b\w/g, function(l) { return l.toUpperCase(); });
            }
        }
        
        return 'Table';
    }

    /**
     * Check has-many tables for hidden required fields
     */
    function checkHasManyTableValidation() {
        var hiddenRequiredFields = [];
        
        // Find all has-many tables
        $('.has-many-table').each(function() {
            var $table = $(this);
            var tableName = getTableName($table);
            
            // Find all rows (excluding template rows)
            $table.find('tbody tr.has-many-table-row').not('.template').each(function(rowIndex) {
                var $row = $(this);
                var rowNumber = rowIndex + 1;
                
                // Find all required fields in this row
                $row.find('input[required], select[required], textarea[required]').each(function() {
                    var $field = $(this);
                    var fieldLabel = getFieldLabel($field);
                    
                    // Check if field is hidden
                    if (isElementHidden($field) || isElementHidden($field.closest('td'))) {
                        hiddenRequiredFields.push({
                            table: tableName,
                            row: rowNumber,
                            field: fieldLabel,
                            element: $field
                        });
                    }
                });
            });
        });
        
        return hiddenRequiredFields;
    }

    /**
     * Show alert with hidden required fields
     * Priority: toastr (lightweight) > SweetAlert (prettier) > alert (fallback)
     */
    function showHiddenRequiredFieldsAlert(hiddenFields) {
        if (hiddenFields.length === 0) {
            return;
        }
        
        // Group by table
        var groupedByTable = {};
        hiddenFields.forEach(function(item) {
            if (!groupedByTable[item.table]) {
                groupedByTable[item.table] = [];
            }
            groupedByTable[item.table].push(item);
        });
        
        // Build message
        var messageLines = [];
        for (var table in groupedByTable) {
            messageLines.push(table + ':');
            groupedByTable[table].forEach(function(item) {
                messageLines.push('  • 行 ' + item.row + ': ' + item.field);
            });
        }
        var plainMessage = '以下の必須項目が非表示になっています：\n\n' + messageLines.join('\n');
        
        // Try toastr first (lightweight and non-blocking)
        if (typeof toastr !== 'undefined') {
            toastr.error(plainMessage, 'バリデーションエラー', { 
                timeOut: 10000,
                extendedTimeOut: 5000,
                closeButton: true,
                progressBar: true,
                positionClass: 'toast-top-right'
            });
            return;
        }
        
        // Build HTML message for SweetAlert
        var messageHtml = '<div style="text-align: left;"><strong>以下の必須項目が非表示になっており、表示する必要があります：</strong><br><br>';
        
        for (var table in groupedByTable) {
            console.log(table);
            
            messageHtml += '<strong>' + table + ':</strong><ul style="margin: 5px 0 15px 20px;">';
            groupedByTable[table].forEach(function(item) {
                messageHtml += '<li>行 ' + item.row + ': <em>' + item.field + '</em></li>';
            });
            messageHtml += '</ul>';
        }
        
        messageHtml += '</div>';
        
        // Try SweetAlert
        if (typeof swal !== 'undefined') {
            // SweetAlert 1.x
            swal({
                title: 'バリデーションエラー',
                text: messageHtml,
                html: true,
                type: 'error',
                confirmButtonText: 'OK'
            });
        } else if (typeof Swal !== 'undefined') {
            // SweetAlert 2.x
            Swal.fire({
                title: 'バリデーションエラー',
                html: messageHtml,
                icon: 'error',
                confirmButtonText: 'OK'
            });
        } else {
            // Fallback to native alert
            alert(plainMessage);
        }
    }

    /**
     * Initialize validation check on form submit
     */
    function initHasManyTableValidation() {
        // Find the form containing has-many tables
        var $form = $('form').has('.has-many-table');
        
        if ($form.length === 0) {
            return;
        }
        
        // Intercept form submit
        $form.on('submit', function(e) {
            // Check for hidden required fields
            var hiddenRequiredFields = checkHasManyTableValidation();
            
            if (hiddenRequiredFields.length > 0) {
                // Prevent form submission
                e.preventDefault();
                e.stopImmediatePropagation();
                console.log(hiddenRequiredFields);
                
                
                // Show alert
                showHiddenRequiredFieldsAlert(hiddenRequiredFields);
                
                // Scroll to first problematic field
                if (hiddenRequiredFields[0].element) {
                    $('html, body').animate({
                        scrollTop: hiddenRequiredFields[0].element.closest('.has-many-table-div').offset().top - 100
                    }, 500);
                }
                
                return false;
            }
        });
        
        // Also check on any custom form submission (like PJAX)
        $(document).on('pjax:beforeSend', function(e) {
            if ($(e.relatedTarget).closest('form').has('.has-many-table').length > 0) {
                var hiddenRequiredFields = checkHasManyTableValidation();
                
                if (hiddenRequiredFields.length > 0) {
                    e.preventDefault();
                    showHiddenRequiredFieldsAlert(hiddenRequiredFields);
                    return false;
                }
            }
        });
    }

    // Initialize when document is ready
    $(function() {
        initHasManyTableValidation();
    });

})(jQuery);
