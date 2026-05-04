<?php
session_start();
require 'config.php';

// Check if admin is logged in
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

// Get drive_id from URL
$drive_id = isset($_GET['drive_id']) ? intval($_GET['drive_id']) : 0;

if ($drive_id === 0) {
    die("Invalid drive ID");
}

// Fetch drive details
$stmt = $conn->prepare("SELECT * FROM drives WHERE drive_id = ?");
$stmt->bind_param("i", $drive_id);
$stmt->execute();
$drive = $stmt->get_result()->fetch_assoc();

if (!$drive) {
    die("Drive not found");
}

// Default form fields (from form_generator.php)
$default_fields = [
    'personal' => [
        "Full Name", "First Name", "Middle Name", "Last Name",
        "Phone No", "Alternate Phone No",
        "Email ID (please recheck the email before submitting)", "Alternate Email ID",
        "Gender", "DOB",
        "Hometown", "State", "District",
        "City (Currently Residing In)", "Pincode",
        "Current Address", "Permanent Address",
        "PAN No", "Aadhar No", "Passport No",
        "Nationality", "Category (General/OBC/SC/ST)", "Religion", "Blood Group",
        "Marital Status", "Father's Name", "Mother's Name",
        "Emergency Contact Name", "Emergency Contact Number"
    ],
    'education' => [
        "--- 10th Details ---",
        "10th grade %", "10th Board Name", "10th Year Of Passing", "10th School Name", "10th School Location",
        "--- 12th Details ---",
        "12th grade/PUC %", "12th Board Name", "12th Year Of Passing", "12th/PUC School Name", "12th/PUC Location", "12th Stream",
        "--- Diploma Details (If any) ---",
        "Diploma %", "Diploma Year Of Passing", "Diploma Specialization", "Diploma College Name", "Diploma University Name",
        "--- UG Details ---",
        "UG Degree", "UG Course Name", "UG %/CGPA", "UG Year of Passing",
        "UG Stream/Specialization", "UG College Name", "UG College Location",
        "UG University Name", "Active Backlogs (UG)?", "No. of Backlogs (UG)",
        "--- PG Details (If any) ---",
        "PG Degree", "PG Course Name", "PG %/CGPA", "PG Year of Passing",
        "PG Stream/Specialization", "PG College Name", "PG College Location",
        "PG University Name", "Active Backlogs (PG)?", "No. of Backlogs (PG)"
    ],
    'work' => [
        "--- Internship Details ---",
        "Have you completed any internship?", "No. of Months of Internship", "Name of Organization",
        "Internship Role", "Internship Project Details", "Internship Location",
        "Certificate of Internship",
        "--- Full-time Experience ---",
        "Have Prior Full-time Experience?", "Company Name", "Designation", "Duration (Months)",
        "Job Role Description", "Last Drawn Salary (If any)", "Reason for Leaving (If any)",
        "--- Projects ---",
        "Project Title", "Project Description", "Technologies Used",
        "GitHub/Project URL", "Upload Portfolio", "Upload Cover Letter",
        "LinkedIn Profile", "Dribbble/Behance Link",
        "--- Preferences ---",
        "Are you available in person for interview?", "Are you ok with relocation?",
        "Are you ok with shifts?", "Willing to join Immediately ?", "Preferred Work Locations?",
        "Preferred Industry", "Preferred Job Role"
    ],
    'others' => [
        "--- Skillset & Certifications ---",
        "Key Skills", "Name of Certifications Completed", "Certifications Upload",
        "Technical Skills", "Programming Languages Known",
        "Languages Known (Read/Write/Speak)",
        "--- Documents Uploads ---",
        "Upload Photo", "Upload Academic Certificates",
        "Upload ID Proof", "Upload Signature", "Additional Documents (You can upload multiple files Ex: Project, Portfolio)",
        "--- Declarations ---",
        "I hereby undertake that I will appear for the complete recruitment process of the above mentioned organization. In case I fail to appear for the same, my candidature for next companies stands cancelled. I also confirm that I have not been placed so far.",
        "I hereby declare that the above information is correct.",
        "Declaration of Authenticity",
        "Agree to Terms and Conditions"
    ]
];

// Get existing custom fields for this drive. Two on-disk formats are possible:
//   (legacy)  {"enabled_fields": {category: [names]}, "custom_fields": [...]}
//   (flat)    [{"name": "...", "type": "...", "options": "...", "mandatory": 0/1}]
// Normalize both into the legacy shape that this UI uses for rendering checkboxes.
$custom_fields = [];
// Map of field name => 1 for any field stored as mandatory in the saved data.
// Custom fields carry their mandatory flag inline on the custom field row.
$mandatory_lookup = [];
if (!empty($drive['form_fields'])) {
    $decoded = json_decode($drive['form_fields'], true);
    if (is_array($decoded)) {
        if (isset($decoded['enabled_fields'])) {
            $custom_fields = $decoded;
            // legacy shape may have its own mandatory_lookup if we wrote it previously
            if (!empty($decoded['mandatory_lookup']) && is_array($decoded['mandatory_lookup'])) {
                foreach ($decoded['mandatory_lookup'] as $mn) {
                    if (is_string($mn)) $mandatory_lookup[$mn] = 1;
                }
            }
        } else {
            // Flat list — reconstruct enabled_fields by matching field names against $default_fields.
            $known_names = [];
            foreach ($default_fields as $cat => $fnames) {
                foreach ($fnames as $fn) {
                    if (strpos($fn, '---') === false) {
                        $known_names[$fn] = $cat;
                    }
                }
            }
            $enabled = ['personal' => [], 'education' => [], 'work' => [], 'others' => []];
            $customs = [];
            foreach ($decoded as $field) {
                if (!is_array($field) || empty($field['name'])) continue;
                $name = $field['name'];
                $is_mand = !empty($field['mandatory']) ? 1 : 0;
                if ($is_mand) $mandatory_lookup[$name] = 1;
                if (isset($known_names[$name])) {
                    $enabled[$known_names[$name]][] = $name;
                } else {
                    $customs[] = [
                        'name'      => $name,
                        'category'  => 'others',
                        'type'      => $field['type'] ?? 'text',
                        'mandatory' => $is_mand,
                    ];
                }
            }
            $custom_fields = ['enabled_fields' => $enabled, 'custom_fields' => $customs];
        }
    }
}

// If no custom fields set, use all default fields
if (empty($custom_fields)) {
    $custom_fields = [
        'enabled_fields' => $default_fields,
        'custom_fields' => []
    ];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_fields'])) {
    $enabled_fields = [
        'personal' => [],
        'education' => [],
        'work' => [],
        'others' => []
    ];

    // Get selected fields from checkboxes
    foreach ($default_fields as $category => $fields) {
        foreach ($fields as $field) {
            $field_key = $category . '_' . md5($field);
            if (isset($_POST[$field_key])) {
                $enabled_fields[$category][] = $field;
            }
        }
    }

    // Get custom fields
    $custom_field_names = $_POST['custom_field_name'] ?? [];
    $custom_field_categories = $_POST['custom_field_category'] ?? [];
    $custom_field_types = $_POST['custom_field_type'] ?? [];
    $custom_field_mandatory = $_POST['custom_field_mandatory'] ?? [];

    $custom_fields_data = [];
    foreach ($custom_field_names as $index => $name) {
        if (!empty($name)) {
            $custom_fields_data[] = [
                'name'      => $name,
                'category'  => $custom_field_categories[$index] ?? 'others',
                'type'      => $custom_field_types[$index] ?? 'text',
                'mandatory' => !empty($custom_field_mandatory[$index]) ? 1 : 0,
            ];
        }
    }

    // Rebuild mandatory lookup from POST: any default field whose mandatory_<key>
    // checkbox is set, plus any custom field marked mandatory in its row.
    $mandatory_lookup = [];
    foreach ($default_fields as $category => $fields) {
        foreach ($fields as $field) {
            if (strpos($field, '---') !== false) continue;
            $field_key = $category . '_' . md5($field);
            if (!empty($_POST['mandatory_' . $field_key])) {
                $mandatory_lookup[$field] = 1;
            }
        }
    }
    foreach ($custom_fields_data as $cf) {
        if (!empty($cf['mandatory'])) {
            $mandatory_lookup[$cf['name']] = 1;
        }
    }

    // Build flat list of field objects — this is the format form_generator.php,
    // add_drive.php and edit_drive.php all read. Saving in this shape keeps the
    // application form rendering consistent regardless of where fields are edited.
    $flat_fields = [];
    foreach (['personal', 'education', 'work', 'others'] as $cat) {
        foreach ($enabled_fields[$cat] ?? [] as $field_name) {
            if (strpos($field_name, '---') !== false) continue;
            $flat_fields[] = [
                'name'      => $field_name,
                'type'      => 'text',
                'options'   => '',
                'mandatory' => !empty($mandatory_lookup[$field_name]) ? 1 : 0,
                'section'   => $cat,
            ];
        }
    }
    foreach ($custom_fields_data as $cf) {
        $flat_fields[] = [
            'name'      => $cf['name'],
            'type'      => $cf['type'] ?? 'text',
            'options'   => '',
            'mandatory' => !empty($cf['mandatory']) ? 1 : 0,
            'section'   => $cf['category'] ?? 'others',
        ];
    }
    $form_fields_json = json_encode($flat_fields);

    // Update database
    $update_stmt = $conn->prepare("UPDATE drives SET form_fields = ? WHERE drive_id = ?");
    $update_stmt->bind_param("si", $form_fields_json, $drive_id);

    if ($update_stmt->execute()) {
        $success_message = "Form fields updated successfully!";
        // Refresh custom_fields variable
        $custom_fields = [
            'enabled_fields' => $enabled_fields,
            'custom_fields' => $custom_fields_data
        ];
    } else {
        $error_message = "Error updating form fields: " . $conn->error;
    }
}

require 'header.php';
?>

<style>
.field-category {
    margin-bottom: 30px;
}

.field-category-header {
    background: #650000;
    color: white;
    padding: 10px 15px;
    border-radius: 5px;
    margin-bottom: 15px;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.field-category-header:hover {
    background: #7a0000;
}

.field-list {
    padding-left: 20px;
    max-height: 400px;
    overflow-y: auto;
}

.field-item {
    padding: 8px 10px;
    border-bottom: 1px solid #eee;
    display: flex;
    align-items: center;
}

.field-item:hover {
    background: #f8f9fa;
}

.field-item input[type="checkbox"] {
    margin-right: 10px;
    width: 18px;
    height: 18px;
}

.mandatory-toggle {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    margin-left: 12px;
    color: #d00;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
}

.mandatory-toggle input[type="checkbox"] {
    width: 16px;
    height: 16px;
    margin-right: 0;
    accent-color: #d00;
}

.custom-field-row .mandatory-toggle {
    margin-left: 0;
    flex-shrink: 0;
}

.field-item.header-row {
    font-weight: bold;
    background: #f0f0f0;
    color: #650000;
    border-bottom: 2px solid #650000;
}

.custom-field-row {
    display: flex;
    gap: 10px;
    margin-bottom: 10px;
    align-items: center;
}

.custom-field-row input,
.custom-field-row select {
    flex: 1;
}

.custom-field-row button {
    flex-shrink: 0;
}

.toggle-icon {
    transition: transform 0.3s;
}

.toggle-icon.collapsed {
    transform: rotate(-90deg);
}
</style>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class='bx bx-edit'></i> Edit Form Fields
                    </h5>
                    <small class="text-muted">
                        <?php echo htmlspecialchars($drive['company_name']); ?> (Drive <?php echo $drive['drive_no']; ?>)
                    </small>
                </div>
                <div class="card-body">
                    <?php if (isset($success_message)): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class='bx bx-check-circle'></i> <?php echo $success_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class='bx bx-error'></i> <?php echo $error_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <div class="alert alert-info">
                        <i class='bx bx-info-circle'></i>
                        <strong>Instructions:</strong>
                        <ul class="mb-0 mt-2">
                            <li>Select the fields you want to include in the application form for this drive</li>
                            <li>Uncheck fields that are not relevant to this job posting</li>
                            <li>You can add custom fields specific to this drive</li>
                            <li>Changes will apply immediately to the student application form</li>
                        </ul>
                    </div>

                    <form method="POST" action="">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <button type="button" class="btn btn-sm btn-outline-success" id="selectAllBtn">
                                    <i class='bx bx-check-double'></i> Select All
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger" id="deselectAllBtn">
                                    <i class='bx bx-x'></i> Deselect All
                                </button>
                            </div>
                        </div>

                        <?php foreach ($default_fields as $category => $fields): ?>
                            <div class="field-category">
                                <div class="field-category-header" data-bs-toggle="collapse" data-bs-target="#category-<?php echo $category; ?>">
                                    <span>
                                        <i class='bx bx-folder'></i>
                                        <?php echo ucfirst($category); ?> Fields
                                        <span class="badge bg-light text-dark ms-2"><?php echo count($fields); ?> fields</span>
                                    </span>
                                    <i class='bx bx-chevron-down toggle-icon'></i>
                                </div>
                                <div class="collapse show field-list" id="category-<?php echo $category; ?>">
                                    <?php foreach ($fields as $field): ?>
                                        <?php
                                        $field_key = $category . '_' . md5($field);
                                        $is_header = strpos($field, '---') !== false;
                                        $is_checked = empty($custom_fields['enabled_fields']) ||
                                            in_array($field, $custom_fields['enabled_fields'][$category] ?? []);
                                        $is_mandatory = !empty($mandatory_lookup[$field]);
                                        ?>
                                        <div class="field-item <?php echo $is_header ? 'header-row' : ''; ?>">
                                            <?php if (!$is_header): ?>
                                                <input type="checkbox"
                                                       name="<?php echo $field_key; ?>"
                                                       id="<?php echo $field_key; ?>"
                                                       <?php echo $is_checked ? 'checked' : ''; ?>
                                                       class="field-checkbox">
                                            <?php endif; ?>
                                            <label for="<?php echo $field_key; ?>" class="mb-0 flex-grow-1">
                                                <?php echo htmlspecialchars($field); ?>
                                            </label>
                                            <?php if (!$is_header): ?>
                                                <label class="mandatory-toggle mb-0" title="Mark this field as mandatory in the application form">
                                                    <input type="checkbox"
                                                           name="mandatory_<?php echo $field_key; ?>"
                                                           id="mandatory_<?php echo $field_key; ?>"
                                                           <?php echo $is_mandatory ? 'checked' : ''; ?>
                                                           class="mandatory-checkbox">
                                                    <span>Mandatory</span>
                                                </label>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <!-- Custom Fields Section -->
                        <div class="field-category mt-4">
                            <div class="field-category-header">
                                <span>
                                    <i class='bx bx-plus-circle'></i>
                                    Custom Fields
                                </span>
                            </div>
                            <div class="p-3">
                                <div id="customFieldsContainer">
                                    <?php if (!empty($custom_fields['custom_fields'])): ?>
                                        <?php foreach ($custom_fields['custom_fields'] as $cf_idx => $custom_field): ?>
                                            <?php $cf_mand = !empty($custom_field['mandatory']) || !empty($mandatory_lookup[$custom_field['name']]); ?>
                                            <div class="custom-field-row">
                                                <input type="text" class="form-control" name="custom_field_name[]"
                                                       placeholder="Field Name" value="<?php echo htmlspecialchars($custom_field['name']); ?>">
                                                <select class="form-select" name="custom_field_category[]">
                                                    <option value="personal" <?php echo $custom_field['category'] === 'personal' ? 'selected' : ''; ?>>Personal</option>
                                                    <option value="education" <?php echo $custom_field['category'] === 'education' ? 'selected' : ''; ?>>Education</option>
                                                    <option value="work" <?php echo $custom_field['category'] === 'work' ? 'selected' : ''; ?>>Work</option>
                                                    <option value="others" <?php echo $custom_field['category'] === 'others' ? 'selected' : ''; ?>>Others</option>
                                                </select>
                                                <select class="form-select" name="custom_field_type[]">
                                                    <option value="text" <?php echo $custom_field['type'] === 'text' ? 'selected' : ''; ?>>Text</option>
                                                    <option value="textarea" <?php echo $custom_field['type'] === 'textarea' ? 'selected' : ''; ?>>Textarea</option>
                                                    <option value="number" <?php echo $custom_field['type'] === 'number' ? 'selected' : ''; ?>>Number</option>
                                                    <option value="date" <?php echo $custom_field['type'] === 'date' ? 'selected' : ''; ?>>Date</option>
                                                    <option value="file" <?php echo $custom_field['type'] === 'file' ? 'selected' : ''; ?>>File</option>
                                                </select>
                                                <label class="mandatory-toggle">
                                                    <input type="checkbox" class="custom-field-mandatory-toggle" <?php echo $cf_mand ? 'checked' : ''; ?>>
                                                    <span>Mandatory</span>
                                                </label>
                                                <input type="hidden" name="custom_field_mandatory[]" value="<?php echo $cf_mand ? '1' : '0'; ?>" class="custom-field-mandatory-hidden">
                                                <button type="button" class="btn btn-danger btn-sm remove-field-btn">
                                                    <i class='bx bx-trash'></i>
                                                </button>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="addCustomFieldBtn">
                                    <i class='bx bx-plus'></i> Add Custom Field
                                </button>
                            </div>
                        </div>

                        <div class="mt-4">
                            <button type="submit" name="save_fields" class="btn btn-primary">
                                <i class='bx bx-save'></i> Save Form Configuration
                            </button>
                            <a href="edit_drive.php?id=<?php echo $drive_id; ?>" class="btn btn-secondary">
                                <i class='bx bx-arrow-back'></i> Back to Drive
                            </a>
                            <a href="form_generator.php?form=<?php echo $drive['form_link']; ?>" class="btn btn-info" target="_blank">
                                <i class='bx bx-show'></i> Preview Form
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Toggle icon rotation on collapse
    $('.field-category-header').on('click', function() {
        $(this).find('.toggle-icon').toggleClass('collapsed');
    });

    // Select All
    $('#selectAllBtn').on('click', function() {
        $('.field-checkbox').prop('checked', true);
    });

    // Deselect All
    $('#deselectAllBtn').on('click', function() {
        $('.field-checkbox').prop('checked', false);
    });

    // Add custom field
    $('#addCustomFieldBtn').on('click', function() {
        const html = `
            <div class="custom-field-row">
                <input type="text" class="form-control" name="custom_field_name[]" placeholder="Field Name" required>
                <select class="form-select" name="custom_field_category[]">
                    <option value="personal">Personal</option>
                    <option value="education">Education</option>
                    <option value="work">Work</option>
                    <option value="others" selected>Others</option>
                </select>
                <select class="form-select" name="custom_field_type[]">
                    <option value="text" selected>Text</option>
                    <option value="textarea">Textarea</option>
                    <option value="number">Number</option>
                    <option value="date">Date</option>
                    <option value="file">File</option>
                </select>
                <label class="mandatory-toggle">
                    <input type="checkbox" class="custom-field-mandatory-toggle">
                    <span>Mandatory</span>
                </label>
                <input type="hidden" name="custom_field_mandatory[]" value="0" class="custom-field-mandatory-hidden">
                <button type="button" class="btn btn-danger btn-sm remove-field-btn">
                    <i class='bx bx-trash'></i>
                </button>
            </div>
        `;
        $('#customFieldsContainer').append(html);
    });

    // Sync custom-field mandatory checkbox to its sibling hidden input
    $(document).on('change', '.custom-field-mandatory-toggle', function() {
        const hidden = $(this).closest('.custom-field-row').find('.custom-field-mandatory-hidden');
        hidden.val(this.checked ? '1' : '0');
    });

    // Remove custom field
    $(document).on('click', '.remove-field-btn', function() {
        $(this).closest('.custom-field-row').remove();
    });
});
</script>

<?php require 'footer.php'; ?>
