<?php
namespace EauSystem;

/**
 * Sistema de Documentação para Admin
 * Páginas de ajuda para usuários finais
 */
class Eau_Documentation {

    /**
     * Registra páginas no menu do admin
     */
    public static function register_admin_pages() {
        // Página principal: Shortcodes
        add_menu_page(
            'EAU Documentation',           // Page title
            'EAU Docs',                    // Menu title
            'manage_options',              // Capability
            'eau-documentation',           // Menu slug
            array(__CLASS__, 'render_shortcodes_page'), // Callback
            'dashicons-book-alt',          // Icon
            60                             // Position
        );

        // Subpágina: Shortcodes
        add_submenu_page(
            'eau-documentation',
            'Available Shortcodes',
            'Shortcodes',
            'manage_options',
            'eau-documentation',
            array(__CLASS__, 'render_shortcodes_page')
        );

        // Subpágina: Features Documentation
        add_submenu_page(
            'eau-documentation',
            'Features Documentation',
            'Features',
            'manage_options',
            'eau-features-docs',
            array(__CLASS__, 'render_features_page')
        );
    }

    /**
     * Enfileira assets para páginas de documentação
     */
    public static function enqueue_admin_assets($hook) {
        // Só carrega nas páginas de documentação
        if (strpos($hook, 'eau-documentation') === false && strpos($hook, 'eau-features-docs') === false) {
            return;
        }

        wp_enqueue_style(
            'eau-documentation',
            EAU_SYSTEM_PLUGIN_URL . 'assets/css/eau-documentation.css',
            array(),
            EAU_SYSTEM_VERSION
        );

        // Prism.js para syntax highlighting
        wp_enqueue_style(
            'prism-css',
            'https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism-tomorrow.min.css',
            array(),
            '1.29.0'
        );

        wp_enqueue_script(
            'prism-js',
            'https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/prism.min.js',
            array(),
            '1.29.0',
            true
        );
    }

    /**
     * Renderiza página de Shortcodes
     */
    public static function render_shortcodes_page() {
        ?>
        <div class="eau-docs-container">
            <div class="eau-docs-header">
                <h1>📋 Available Shortcodes</h1>
                <p class="eau-docs-subtitle">Copy and paste these shortcodes into any page or post to display the functionality.</p>
            </div>

            <div class="eau-docs-content">

                <!-- Admin Dashboard -->
                <div class="eau-doc-card">
                    <div class="eau-doc-card-header">
                        <h2>Admin Dashboard</h2>
                        <span class="eau-doc-badge eau-badge-admin">Admin Only</span>
                    </div>
                    <div class="eau-doc-card-body">
                        <p>Displays a comprehensive dashboard with system statistics including total members, CPD activities, active events, points awarded, and pending payments.</p>

                        <div class="eau-code-block">
                            <div class="eau-code-header">
                                <span>Shortcode</span>
                                <button class="eau-copy-btn" onclick="copyToClipboard('[eau_admin_dashboard]', this)">Copy</button>
                            </div>
                            <pre><code class="language-markup">[eau_admin_dashboard]</code></pre>
                        </div>

                        <div class="eau-doc-features">
                            <h4>Features:</h4>
                            <ul>
                                <li>✅ Real-time statistics cards</li>
                                <li>✅ Responsive design (mobile, tablet, desktop)</li>
                                <li>✅ Personalized welcome message</li>
                                <li>✅ Visual icons with color coding</li>
                            </ul>
                        </div>

                        <div class="eau-doc-example">
                            <h4>Use Case:</h4>
                            <p>Add to a page called "Dashboard" and set it as the homepage for logged-in administrators.</p>
                        </div>
                    </div>
                </div>

                <!-- Members Management -->
                <div class="eau-doc-card">
                    <div class="eau-doc-card-header">
                        <h2>Members Management</h2>
                        <span class="eau-doc-badge eau-badge-admin">Admin Only</span>
                    </div>
                    <div class="eau-doc-card-body">
                        <p>Complete member management system with search, filters, sorting, pagination, and CRUD operations.</p>

                        <div class="eau-code-block">
                            <div class="eau-code-header">
                                <span>Shortcode</span>
                                <button class="eau-copy-btn" onclick="copyToClipboard('[eau_members_management]', this)">Copy</button>
                            </div>
                            <pre><code class="language-markup">[eau_members_management]</code></pre>
                        </div>

                        <div class="eau-doc-features">
                            <h4>Features:</h4>
                            <ul>
                                <li>✅ Search by name, email, or phone</li>
                                <li>✅ Filter by membership status and type</li>
                                <li>✅ Sort by any column (name, email, status, etc)</li>
                                <li>✅ Pagination with configurable items per page</li>
                                <li>✅ Create, Edit, View, and Delete members</li>
                                <li>✅ Modal-based forms with validation</li>
                                <li>✅ Skeleton loading states</li>
                                <li>✅ Toast notifications for feedback</li>
                                <li>✅ Configurable fields (via Settings)</li>
                            </ul>
                        </div>

                        <div class="eau-doc-example">
                            <h4>Use Case:</h4>
                            <p>Add to a page called "Members" accessible only to administrators for complete member control.</p>
                        </div>

                        <div class="eau-doc-note">
                            <strong>📝 Note:</strong> Configure which fields are editable in <code>Eau System → Members Settings</code>
                        </div>
                    </div>
                </div>

                <!-- Institutions Management -->
                <div class="eau-doc-card">
                    <div class="eau-doc-card-header">
                        <h2>Institutions Management</h2>
                        <span class="eau-doc-badge eau-badge-admin">Admin Only</span>
                    </div>
                    <div class="eau-doc-card-body">
                        <p>Complete institution management system with CRUD operations, search, filters, and member tracking.</p>

                        <div class="eau-code-block">
                            <div class="eau-code-header">
                                <span>Shortcode</span>
                                <button class="eau-copy-btn" onclick="copyToClipboard('[eau_institutions_management]', this)">Copy</button>
                            </div>
                            <pre><code class="language-markup">[eau_institutions_management]</code></pre>
                        </div>

                        <div class="eau-doc-features">
                            <h4>Features:</h4>
                            <ul>
                                <li>✅ Search by institution name or code</li>
                                <li>✅ Filter by status (active/inactive) and creation date</li>
                                <li>✅ Sort by any column (name, code, status, etc)</li>
                                <li>✅ Pagination with 20 items per page</li>
                                <li>✅ Create, Edit, View, and Delete institutions</li>
                                <li>✅ Member count tracking per institution</li>
                                <li>✅ Export to CSV (all or selected)</li>
                                <li>✅ Skeleton loading states</li>
                                <li>✅ Toast notifications for feedback</li>
                                <li>✅ Delete protection (cannot delete with active members)</li>
                            </ul>
                        </div>

                        <div class="eau-doc-example">
                            <h4>Use Case:</h4>
                            <p>Add to a page called "Institutions" accessible only to administrators for complete institution control.</p>
                        </div>

                        <div class="eau-doc-note">
                            <strong>📝 Note:</strong> Integrated with JetEngine CCT table <code>wp_jet_cct_institutions</code>
                        </div>
                    </div>
                </div>

                <!-- Activities Management -->
                <div class="eau-doc-card">
                    <div class="eau-doc-card-header">
                        <h2>Activities Management</h2>
                        <span class="eau-doc-badge eau-badge-admin">Admin Only</span>
                    </div>
                    <div class="eau-doc-card-body">
                        <p>Complete management interface for CPD (Continuing Professional Development) activities with verification workflow and hours tracking.</p>

                        <div class="eau-code-block">
                            <div class="eau-code-header">
                                <span>Shortcode</span>
                                <button class="eau-copy-btn" onclick="copyToClipboard('[eau_activities_management]', this)">Copy</button>
                            </div>
                            <pre><code class="language-markup">[eau_activities_management]</code></pre>
                        </div>

                        <div class="eau-doc-features">
                            <h4>Features:</h4>
                            <ul>
                                <li>✅ Real-time stats (Total, Verified, Pending, Total Hours)</li>
                                <li>✅ Advanced search and filtering</li>
                                <li>✅ Filter by verification status</li>
                                <li>✅ Filter by institution</li>
                                <li>✅ Filter by date range</li>
                                <li>✅ Sortable columns (Activity, Member, Institution, Hours, Date)</li>
                                <li>✅ Create, view, edit, and delete activities</li>
                                <li>✅ Verification workflow (Pending/Verified)</li>
                                <li>✅ Hours tracking with decimal precision</li>
                                <li>✅ CSV export with all filters applied</li>
                                <li>✅ Institution Admin scope (see only their members' activities)</li>
                                <li>✅ Paginated results (30 per page)</li>
                            </ul>
                        </div>

                        <div class="eau-doc-example">
                            <h4>Use Case:</h4>
                            <p>Add to a page called "CPD Activities" for administrators and institution admins to manage, verify, and track professional development activities.</p>
                        </div>

                        <div class="eau-doc-note">
                            <strong>📝 Note:</strong> Activities belong to members via WordPress <code>post_author</code> field. Each activity tracks hours and verification status.
                        </div>
                    </div>
                </div>

                <!-- Categories Management -->
                <div class="eau-doc-card">
                    <div class="eau-doc-card-header">
                        <h2>Categories Management</h2>
                        <span class="eau-doc-badge eau-badge-admin">Admin Only</span>
                    </div>
                    <div class="eau-doc-card-body">
                        <p>Configure activity categories and assign points per hour for CPD activities tracking.</p>

                        <div class="eau-code-block">
                            <div class="eau-code-header">
                                <span>Shortcode</span>
                                <button class="eau-copy-btn" onclick="copyToClipboard('[eau_categories_management]', this)">Copy</button>
                            </div>
                            <pre><code class="language-markup">[eau_categories_management]</code></pre>
                        </div>

                        <div class="eau-doc-features">
                            <h4>Features:</h4>
                            <ul>
                                <li>✅ Search by category name or ID</li>
                                <li>✅ Sort by any column (ID, Name, Points/Hour, Date)</li>
                                <li>✅ Pagination with 20 items per page</li>
                                <li>✅ Create, Edit, View, and Delete categories</li>
                                <li>✅ Refresh/Sync from existing activities</li>
                                <li>✅ Configure points per hour for each category</li>
                                <li>✅ Statistics cards (Total, Configured, Not Configured, Avg Points)</li>
                                <li>✅ Skeleton loading states</li>
                                <li>✅ Toast notifications for feedback</li>
                            </ul>
                        </div>

                        <div class="eau-doc-example">
                            <h4>Use Case:</h4>
                            <p>Add to a page called "Categories" accessible only to administrators to configure point values for different activity categories.</p>
                        </div>

                        <div class="eau-doc-note">
                            <strong>📝 Note:</strong> Categories are automatically extracted from existing activities using <code>act_category_serial</code> and <code>categoria_de_ato</code> fields.
                        </div>
                    </div>
                </div>

                <!-- Duplicate Manager -->
                <div class="eau-doc-card">
                    <div class="eau-doc-card-header">
                        <h2>Duplicate Manager</h2>
                        <span class="eau-doc-badge eau-badge-admin">Admin Only</span>
                    </div>
                    <div class="eau-doc-card-body">
                        <p>Intelligent system to detect and merge duplicate members using advanced similarity algorithms.</p>

                        <div class="eau-code-block">
                            <div class="eau-code-header">
                                <span>Shortcode</span>
                                <button class="eau-copy-btn" onclick="copyToClipboard('[eau_duplicate_manager]', this)">Copy</button>
                            </div>
                            <pre><code class="language-markup">[eau_duplicate_manager]</code></pre>
                        </div>

                        <div class="eau-doc-features">
                            <h4>Features:</h4>
                            <ul>
                                <li>✅ One-click scan of all members</li>
                                <li>✅ AI-powered similarity detection (7 fields analyzed)</li>
                                <li>✅ Score-based matching (50-100%)</li>
                                <li>✅ Side-by-side comparison</li>
                                <li>✅ Selective merge (choose which data to keep)</li>
                                <li>✅ Mark as "Not Duplicate"</li>
                                <li>✅ Ignore permanently in future scans</li>
                                <li>✅ Filter by match confidence (High/Medium)</li>
                                <li>✅ Complete audit trail</li>
                            </ul>
                        </div>

                        <div class="eau-doc-algorithm">
                            <h4>Algorithm Details:</h4>
                            <p>Analyzes these fields with weighted scoring:</p>
                            <ul>
                                <li><strong>Name (25%):</strong> Levenshtein distance + Soundex phonetic matching</li>
                                <li><strong>Email (20%):</strong> Domain match + username similarity</li>
                                <li><strong>Phone (15%):</strong> Normalized comparison</li>
                                <li><strong>Company (15%):</strong> Exact + fuzzy matching</li>
                                <li><strong>Postcode (10%):</strong> Exact + regional matching</li>
                                <li><strong>Address (10%):</strong> Fuzzy text comparison</li>
                                <li><strong>City (5%):</strong> Exact + fuzzy matching</li>
                            </ul>
                        </div>

                        <div class="eau-doc-example">
                            <h4>Use Case:</h4>
                            <p>Add to a page called "Duplicate Checker" for administrators to periodically scan and clean up duplicate member records.</p>
                        </div>

                        <div class="eau-doc-warning">
                            <strong>⚠️ Warning:</strong> Merge operations cannot be undone. One member will be permanently deleted.
                        </div>
                    </div>
                </div>

                <!-- My CPDs -->
                <div class="eau-doc-card">
                    <div class="eau-doc-card-header">
                        <h2>My CPDs</h2>
                        <span class="eau-doc-badge eau-badge-member">All Members</span>
                    </div>
                    <div class="eau-doc-card-body">
                        <p>Personal CPD tracking page where members can view their activities, track progress towards annual goals, and submit new activities for approval.</p>

                        <div class="eau-code-block">
                            <div class="eau-code-header">
                                <span>Shortcode</span>
                                <button class="eau-copy-btn" onclick="copyToClipboard('[eau_my_cpds]', this)">Copy</button>
                            </div>
                            <pre><code class="language-markup">[eau_my_cpds]</code></pre>
                        </div>

                        <div class="eau-doc-features">
                            <h4>Features:</h4>
                            <ul>
                                <li>✅ Annual progress ring (20 points goal)</li>
                                <li>✅ Statistics cards (Total Points, Activities, Verified, Pending)</li>
                                <li>✅ Searchable activities table</li>
                                <li>✅ Filter by status and category</li>
                                <li>✅ Sort by any column</li>
                                <li>✅ View activity details modal</li>
                                <li>✅ Add new activity with file upload</li>
                                <li>✅ Edit pending activities</li>
                                <li>✅ Delete pending activities</li>
                                <li>✅ Proof upload (file or URL)</li>
                                <li>✅ WYSIWYG description editor</li>
                                <li>✅ Skeleton loading states</li>
                                <li>✅ Toast notifications</li>
                            </ul>
                        </div>

                        <div class="eau-doc-example">
                            <h4>Use Case:</h4>
                            <p>Add to a page called "My CPDs" in the member dashboard area. Each member sees only their own CPD activities and progress.</p>
                        </div>

                        <div class="eau-doc-note">
                            <strong>📝 Note:</strong> Members can only edit or delete activities with "Pending" status. Verified activities are read-only.
                        </div>
                    </div>
                </div>

                <!-- My Profile -->
                <div class="eau-doc-card">
                    <div class="eau-doc-card-header">
                        <h2>My Profile</h2>
                        <span class="eau-doc-badge eau-badge-member">All Members</span>
                    </div>
                    <div class="eau-doc-card-body">
                        <p>Personal profile page where members can view and update their personal information, address, and account settings.</p>

                        <div class="eau-code-block">
                            <div class="eau-code-header">
                                <span>Shortcode</span>
                                <button class="eau-copy-btn" onclick="copyToClipboard('[eau_my_profile]', this)">Copy</button>
                            </div>
                            <pre><code class="language-markup">[eau_my_profile]</code></pre>
                        </div>

                        <div class="eau-doc-features">
                            <h4>Features:</h4>
                            <ul>
                                <li>✅ Profile photo with upload functionality</li>
                                <li>✅ Personal information section (name, email, phone, bio)</li>
                                <li>✅ Address section (street, city, postcode)</li>
                                <li>✅ Account information (username, member ID, type, status)</li>
                                <li>✅ Change password functionality</li>
                                <li>✅ International phone input with country selector</li>
                                <li>✅ Modal-based editing</li>
                                <li>✅ Real-time validation</li>
                                <li>✅ Toast notifications</li>
                                <li>✅ Responsive design</li>
                            </ul>
                        </div>

                        <div class="eau-doc-example">
                            <h4>Use Case:</h4>
                            <p>Add to a page called "Profile" in the member dashboard area. Each member can view and edit their own profile information.</p>
                        </div>

                        <div class="eau-doc-note">
                            <strong>📝 Note:</strong> Some fields like Username, Member ID, and User Type are read-only and cannot be changed by the member.
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <script>
        function copyToClipboard(text, button) {
            navigator.clipboard.writeText(text).then(function() {
                const originalText = button.textContent;
                button.textContent = 'Copied!';
                button.classList.add('copied');

                setTimeout(function() {
                    button.textContent = originalText;
                    button.classList.remove('copied');
                }, 2000);
            }).catch(function(err) {
                console.error('Failed to copy: ', err);
            });
        }
        </script>
        <?php
    }

    /**
     * Renderiza página de Features Documentation
     */
    public static function render_features_page() {
        ?>
        <div class="eau-docs-container">
            <div class="eau-docs-header">
                <h1>📚 Features Documentation</h1>
                <p class="eau-docs-subtitle">Complete guide to all system features and functionalities.</p>
            </div>

            <div class="eau-docs-content">

                <!-- Admin Dashboard Feature -->
                <div class="eau-doc-card">
                    <div class="eau-doc-card-header">
                        <h2>1. Admin Dashboard</h2>
                        <span class="eau-doc-badge eau-badge-v1">v1.0.0+</span>
                    </div>
                    <div class="eau-doc-card-body">
                        <h3>Overview</h3>
                        <p>The Admin Dashboard provides a comprehensive overview of your system's key metrics in a visual, easy-to-understand format.</p>

                        <h3>Statistics Cards</h3>
                        <div class="eau-doc-grid">
                            <div class="eau-doc-stat">
                                <h4>Total Members</h4>
                                <p>Shows total registered users and how many are currently active.</p>
                            </div>
                            <div class="eau-doc-stat">
                                <h4>CPD Activities</h4>
                                <p>Displays published activities and those pending approval.</p>
                            </div>
                            <div class="eau-doc-stat">
                                <h4>Active Events</h4>
                                <p>Count of upcoming events (event_date >= today).</p>
                            </div>
                            <div class="eau-doc-stat">
                                <h4>Points Awarded</h4>
                                <p>Total CPD hours/points awarded across all activities.</p>
                            </div>
                        </div>

                        <h3>Responsive Design</h3>
                        <ul>
                            <li><strong>Mobile:</strong> 1 column layout</li>
                            <li><strong>Tablet:</strong> 2 columns layout</li>
                            <li><strong>Desktop:</strong> 4 columns layout</li>
                        </ul>

                        <h3>Customization</h3>
                        <p>Modify statistics in: <code>includes/class-eau-dashboard.php</code></p>
                    </div>
                </div>

                <!-- Members Management Feature -->
                <div class="eau-doc-card">
                    <div class="eau-doc-card-header">
                        <h2>2. Members Management</h2>
                        <span class="eau-doc-badge eau-badge-v1">v1.9.0+</span>
                    </div>
                    <div class="eau-doc-card-body">
                        <h3>Overview</h3>
                        <p>A complete CRUD system for managing your members with advanced filtering, searching, and sorting capabilities.</p>

                        <h3>Key Features</h3>

                        <h4>🔍 Search</h4>
                        <p>Real-time search across:</p>
                        <ul>
                            <li>Display name</li>
                            <li>Email address</li>
                            <li>Phone number</li>
                        </ul>

                        <h4>🎯 Filters</h4>
                        <ul>
                            <li><strong>Membership Status:</strong> All, Active, Inactive, Pending, Suspended</li>
                            <li><strong>Membership Type:</strong> All types configured in system</li>
                        </ul>

                        <h4>📊 Sorting</h4>
                        <p>Click any column header to sort (ascending/descending):</p>
                        <ul>
                            <li>Name</li>
                            <li>Email</li>
                            <li>Status</li>
                            <li>Type</li>
                            <li>Registration Date</li>
                        </ul>

                        <h4>✏️ CRUD Operations</h4>
                        <ul>
                            <li><strong>Create:</strong> Add new members with validated forms</li>
                            <li><strong>Edit:</strong> Update existing member information</li>
                            <li><strong>View:</strong> Read-only view of member details</li>
                            <li><strong>Delete:</strong> Remove members with confirmation</li>
                        </ul>

                        <h3>Field Configuration</h3>
                        <p>Navigate to <strong>Eau System → Members Settings</strong> to:</p>
                        <ul>
                            <li>Enable/disable fields</li>
                            <li>Mark fields as required</li>
                            <li>Set fields as read-only</li>
                            <li>Configure field types (text, email, phone, select, etc)</li>
                        </ul>

                        <h3>User Experience</h3>
                        <ul>
                            <li><strong>Skeleton Loading:</strong> Shows placeholders while loading data</li>
                            <li><strong>Toast Notifications:</strong> Success/error feedback</li>
                            <li><strong>Confirm Modals:</strong> Safety confirmations for destructive actions</li>
                            <li><strong>Form Validation:</strong> Client-side and server-side validation</li>
                        </ul>

                        <h3>Permissions</h3>
                        <p>Only users with <code>manage_options</code> capability (Admin and Super Admin) can access this feature.</p>
                    </div>
                </div>

                <!-- Institutions Management Feature -->
                <div class="eau-doc-card">
                    <div class="eau-doc-card-header">
                        <h2>3. Institutions Management</h2>
                        <span class="eau-doc-badge eau-badge-v1">v1.28.0+</span>
                    </div>
                    <div class="eau-doc-card-body">
                        <h3>Overview</h3>
                        <p>A complete CRUD system for managing institutions with advanced filtering, searching, sorting, and member tracking capabilities.</p>

                        <h3>Key Features</h3>

                        <h4>🔍 Search</h4>
                        <p>Real-time search across:</p>
                        <ul>
                            <li>Institution name</li>
                            <li>Institution code</li>
                        </ul>

                        <h4>🎯 Filters</h4>
                        <ul>
                            <li><strong>Status:</strong> All, Active, Inactive</li>
                            <li><strong>Creation Date:</strong> Date range picker (from/to)</li>
                        </ul>

                        <h4>📊 Sorting</h4>
                        <p>Click any column header to sort (ascending/descending):</p>
                        <ul>
                            <li>Institution Name</li>
                            <li>Code</li>
                            <li>Contact Info</li>
                            <li>Member Count</li>
                            <li>Status</li>
                        </ul>

                        <h4>✏️ CRUD Operations</h4>
                        <ul>
                            <li><strong>Create:</strong> Add new institutions with validated forms</li>
                            <li><strong>Edit:</strong> Update existing institution information</li>
                            <li><strong>View:</strong> Read-only view of institution details</li>
                            <li><strong>Delete:</strong> Remove institutions with safety checks</li>
                        </ul>

                        <h3>Institution Fields</h3>
                        <table class="eau-doc-table">
                            <thead>
                                <tr>
                                    <th>Field</th>
                                    <th>Type</th>
                                    <th>Required</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Institution Name</td>
                                    <td>Text</td>
                                    <td>Yes</td>
                                    <td>Full name of the institution</td>
                                </tr>
                                <tr>
                                    <td>Code</td>
                                    <td>Text</td>
                                    <td>Yes</td>
                                    <td>Unique identifier (must be unique)</td>
                                </tr>
                                <tr>
                                    <td>Email</td>
                                    <td>Email</td>
                                    <td>No</td>
                                    <td>Primary contact email</td>
                                </tr>
                                <tr>
                                    <td>Phone</td>
                                    <td>Tel</td>
                                    <td>No</td>
                                    <td>Primary contact phone</td>
                                </tr>
                                <tr>
                                    <td>Address</td>
                                    <td>Text</td>
                                    <td>No</td>
                                    <td>Street address</td>
                                </tr>
                                <tr>
                                    <td>City</td>
                                    <td>Text</td>
                                    <td>No</td>
                                    <td>City name</td>
                                </tr>
                                <tr>
                                    <td>State</td>
                                    <td>Text</td>
                                    <td>No</td>
                                    <td>State/Province</td>
                                </tr>
                                <tr>
                                    <td>ZIP Code</td>
                                    <td>Text</td>
                                    <td>No</td>
                                    <td>Postal/ZIP code</td>
                                </tr>
                                <tr>
                                    <td>Country</td>
                                    <td>Text</td>
                                    <td>No</td>
                                    <td>Country name</td>
                                </tr>
                                <tr>
                                    <td>Status</td>
                                    <td>Select</td>
                                    <td>Yes</td>
                                    <td>Active or Inactive</td>
                                </tr>
                            </tbody>
                        </table>

                        <h3>Member Tracking</h3>
                        <p>Each institution shows the number of members associated with it. The system:</p>
                        <ul>
                            <li>Counts users with matching <code>mem_membercompanyname</code> field</li>
                            <li>Displays count in real-time</li>
                            <li>Shows count in View and Edit modals</li>
                            <li>Prevents deletion if institution has members</li>
                        </ul>

                        <h3>Safety Features</h3>
                        <ul>
                            <li>✅ Code uniqueness validation (no duplicate codes)</li>
                            <li>✅ Delete protection (cannot delete with active members)</li>
                            <li>✅ Confirmation required before delete</li>
                            <li>✅ Form validation (client and server-side)</li>
                            <li>✅ Toast notifications for all actions</li>
                        </ul>

                        <h3>Export Functionality</h3>
                        <p>Export institutions to CSV with options:</p>
                        <ul>
                            <li><strong>Export All:</strong> Exports all institutions with current filters</li>
                            <li><strong>Export Selected:</strong> Exports only checked institutions</li>
                        </ul>
                        <p>CSV includes: ID, Name, Code, Email, Phone, Address, City, State, ZIP, Country, Status, Member Count, Created Date</p>

                        <h3>User Experience</h3>
                        <ul>
                            <li><strong>Skeleton Loading:</strong> Shows placeholders while loading data</li>
                            <li><strong>Toast Notifications:</strong> Success/error feedback</li>
                            <li><strong>Confirm Modals:</strong> Safety confirmations for destructive actions</li>
                            <li><strong>Form Validation:</strong> Client-side and server-side validation</li>
                            <li><strong>Responsive Design:</strong> Works on mobile, tablet, and desktop</li>
                        </ul>

                        <h3>Integration</h3>
                        <p>Fully integrated with JetEngine Content Custom Tables (CCT):</p>
                        <ul>
                            <li>Table: <code>wp_jet_cct_institutions</code></li>
                            <li>Direct database queries for performance</li>
                            <li>Automatic timestamps (created/modified)</li>
                            <li>Author tracking</li>
                        </ul>

                        <h3>Permissions</h3>
                        <p>Only users with <code>manage_options</code> capability (Admin and Super Admin) can access this feature.</p>

                        <h3>Statistics Cards</h3>
                        <div class="eau-doc-grid">
                            <div class="eau-doc-stat">
                                <h4>Total Institutions</h4>
                                <p>Count of all registered institutions.</p>
                            </div>
                            <div class="eau-doc-stat">
                                <h4>Active Institutions</h4>
                                <p>Count of institutions with status = active.</p>
                            </div>
                            <div class="eau-doc-stat">
                                <h4>New This Month</h4>
                                <p>Count of institutions created in current month.</p>
                            </div>
                            <div class="eau-doc-stat">
                                <h4>Inactive Institutions</h4>
                                <p>Count of institutions with status = inactive.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Categories Management Feature -->
                <div class="eau-doc-card">
                    <div class="eau-doc-card-header">
                        <h2>4. Categories Management</h2>
                        <span class="eau-doc-badge eau-badge-v1">v1.31.0+</span>
                    </div>
                    <div class="eau-doc-card-body">
                        <h3>Overview</h3>
                        <p>A complete system for configuring activity categories and assigning points per hour for CPD activities tracking.</p>

                        <h3>Key Features</h3>

                        <h4>🔍 Search</h4>
                        <p>Real-time search across:</p>
                        <ul>
                            <li>Category name</li>
                            <li>Category ID (serial)</li>
                        </ul>

                        <h4>📊 Sorting</h4>
                        <p>Click any column header to sort (ascending/descending):</p>
                        <ul>
                            <li>Category ID</li>
                            <li>Category Name</li>
                            <li>Points per Hour</li>
                            <li>Last Updated</li>
                        </ul>

                        <h4>✏️ CRUD Operations</h4>
                        <ul>
                            <li><strong>Create:</strong> Add new categories manually</li>
                            <li><strong>Edit:</strong> Update category name and points per hour</li>
                            <li><strong>View:</strong> Read-only view of category details</li>
                            <li><strong>Delete:</strong> Remove categories with confirmation</li>
                            <li><strong>Refresh:</strong> Sync categories from existing activities</li>
                        </ul>

                        <h3>Category Fields</h3>
                        <table class="eau-doc-table">
                            <thead>
                                <tr>
                                    <th>Field</th>
                                    <th>Type</th>
                                    <th>Required</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Category ID</td>
                                    <td>Text</td>
                                    <td>Yes</td>
                                    <td>Unique identifier (must be unique)</td>
                                </tr>
                                <tr>
                                    <td>Category Name</td>
                                    <td>Text</td>
                                    <td>Yes</td>
                                    <td>Full name of the category</td>
                                </tr>
                                <tr>
                                    <td>Points per Hour</td>
                                    <td>Decimal</td>
                                    <td>Yes</td>
                                    <td>How many points 1 hour of this activity is worth</td>
                                </tr>
                            </tbody>
                        </table>

                        <h3>Sync from Activities</h3>
                        <p>Click "Refresh from Activities" to automatically:</p>
                        <ul>
                            <li>Extract all distinct categories from published activities</li>
                            <li>Read <code>act_category_serial</code> (Category ID)</li>
                            <li>Read <code>categoria_de_ato</code> (Category Name)</li>
                            <li>Add new categories found (with 0 points by default)</li>
                            <li>Skip categories that already exist</li>
                        </ul>

                        <h3>User Experience</h3>
                        <ul>
                            <li><strong>Skeleton Loading:</strong> Shows placeholders while loading data</li>
                            <li><strong>Toast Notifications:</strong> Success/error feedback</li>
                            <li><strong>Confirm Modals:</strong> Safety confirmations for destructive actions</li>
                            <li><strong>Form Validation:</strong> Client-side and server-side validation</li>
                            <li><strong>Responsive Design:</strong> Works on mobile, tablet, and desktop</li>
                        </ul>

                        <h3>Permissions</h3>
                        <p>Only users with <code>manage_options</code> capability (Admin and Super Admin) can access this feature.</p>

                        <h3>Statistics Cards</h3>
                        <div class="eau-doc-grid">
                            <div class="eau-doc-stat">
                                <h4>Total Categories</h4>
                                <p>Count of all registered categories.</p>
                            </div>
                            <div class="eau-doc-stat">
                                <h4>Configured</h4>
                                <p>Categories with points > 0 configured.</p>
                            </div>
                            <div class="eau-doc-stat">
                                <h4>Not Configured</h4>
                                <p>Categories with 0 points (need configuration).</p>
                            </div>
                            <div class="eau-doc-stat">
                                <h4>Avg Points/Hour</h4>
                                <p>Average points per hour across all configured categories.</p>
                            </div>
                        </div>

                        <h3>Integration</h3>
                        <p>Custom database table:</p>
                        <ul>
                            <li>Table: <code>wp_eau_activity_categories</code></li>
                            <li>Automatic timestamps (created/updated)</li>
                            <li>Unique constraint on category_serial</li>
                        </ul>
                    </div>
                </div>

                <!-- Duplicate Manager Feature -->
                <div class="eau-doc-card">
                    <div class="eau-doc-card-header">
                        <h2>5. Duplicate Manager</h2>
                        <span class="eau-doc-badge eau-badge-v1">v1.18.0+</span>
                    </div>
                    <div class="eau-doc-card-body">
                        <h3>Overview</h3>
                        <p>An intelligent system that automatically detects duplicate members using advanced algorithms and allows safe merging of records.</p>

                        <h3>How It Works</h3>

                        <h4>Step 1: Scan</h4>
                        <p>Click "Start New Scan" to analyze all members. The system will:</p>
                        <ul>
                            <li>Compare every pair of members</li>
                            <li>Calculate similarity scores (0-100%)</li>
                            <li>Identify potential duplicates (score ≥ 50%)</li>
                            <li>Display results sorted by confidence</li>
                        </ul>

                        <h4>Step 2: Review</h4>
                        <p>Each duplicate pair shows:</p>
                        <ul>
                            <li><strong>Similarity Score:</strong> Percentage match with color-coded badge</li>
                            <li><strong>Match Tags:</strong> Which fields are similar (Name, Email, Phone, etc)</li>
                            <li><strong>Side-by-Side Comparison:</strong> Visual diff of all data</li>
                        </ul>

                        <h4>Step 3: Take Action</h4>
                        <p>Three options for each pair:</p>
                        <ul>
                            <li><strong>Merge:</strong> Combine into one member, choose which data to keep</li>
                            <li><strong>Not Duplicate:</strong> They're different people, don't show again</li>
                            <li><strong>Ignore:</strong> Skip this pair in all future scans</li>
                        </ul>

                        <h3>Similarity Algorithm</h3>
                        <p>The system analyzes 7 fields with weighted scoring:</p>
                        <table class="eau-doc-table">
                            <thead>
                                <tr>
                                    <th>Field</th>
                                    <th>Weight</th>
                                    <th>Method</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Display Name</td>
                                    <td>25%</td>
                                    <td>Levenshtein + Soundex</td>
                                </tr>
                                <tr>
                                    <td>Email</td>
                                    <td>20%</td>
                                    <td>Domain + Username Similarity</td>
                                </tr>
                                <tr>
                                    <td>Phone</td>
                                    <td>15%</td>
                                    <td>Normalized Comparison</td>
                                </tr>
                                <tr>
                                    <td>Company</td>
                                    <td>15%</td>
                                    <td>Exact + Fuzzy Match</td>
                                </tr>
                                <tr>
                                    <td>Postcode</td>
                                    <td>10%</td>
                                    <td>Exact + Regional</td>
                                </tr>
                                <tr>
                                    <td>Address</td>
                                    <td>10%</td>
                                    <td>Fuzzy Text</td>
                                </tr>
                                <tr>
                                    <td>City</td>
                                    <td>5%</td>
                                    <td>Exact + Fuzzy</td>
                                </tr>
                            </tbody>
                        </table>

                        <h3>Merge Process</h3>
                        <ol>
                            <li>Click "Merge" on a duplicate pair</li>
                            <li>Modal shows all fields side-by-side</li>
                            <li>Select which version of each field to keep (radio buttons)</li>
                            <li>Confirm merge (with warning)</li>
                            <li>System will:
                                <ul>
                                    <li>Update the kept member with chosen data</li>
                                    <li>Transfer all posts/activities to kept member</li>
                                    <li>Delete the other member</li>
                                    <li>Record the action for audit</li>
                                </ul>
                            </li>
                        </ol>

                        <h3>Safety Features</h3>
                        <ul>
                            <li>✅ Confirmation required before merge</li>
                            <li>✅ Cannot merge if users don't exist</li>
                            <li>✅ Email conflict detection</li>
                            <li>✅ Related content is transferred (not lost)</li>
                            <li>✅ Complete audit trail maintained</li>
                        </ul>

                        <h3>Exclusions System</h3>
                        <p>Two types of exclusions:</p>
                        <ul>
                            <li><strong>Not Duplicate:</strong> Marks pair as different people, won't appear in future scans</li>
                            <li><strong>Ignore:</strong> Permanently excludes pair from all analysis</li>
                        </ul>

                        <h3>Performance</h3>
                        <p>Scan time depends on number of members:</p>
                        <ul>
                            <li>100 members: ~5 seconds</li>
                            <li>500 members: ~30 seconds</li>
                            <li>1,000 members: ~2 minutes</li>
                        </ul>

                        <h3>Best Practices</h3>
                        <ul>
                            <li>Run scans after bulk imports</li>
                            <li>Review high-confidence matches (≥80%) first</li>
                            <li>Always verify data before merging</li>
                            <li>Use "Not Duplicate" liberally to clean up results</li>
                            <li>Keep backup before major merge operations</li>
                        </ul>
                    </div>
                </div>

                <!-- My CPDs Feature -->
                <div class="eau-doc-card">
                    <div class="eau-doc-card-header">
                        <h2>6. My CPDs (Member Area)</h2>
                        <span class="eau-doc-badge eau-badge-v1">v1.37.0+</span>
                    </div>
                    <div class="eau-doc-card-body">
                        <h3>Overview</h3>
                        <p>Personal CPD tracking page where members can view their professional development activities, track progress towards annual goals (20 points), and submit new activities for approval.</p>

                        <h3>Key Features</h3>

                        <h4>📊 Progress Tracking</h4>
                        <ul>
                            <li><strong>Annual Progress Ring:</strong> Visual circular progress indicator showing points earned vs. 20-point annual goal</li>
                            <li><strong>Points Display:</strong> Current points and remaining points needed</li>
                            <li><strong>Year Context:</strong> Shows current calendar year for tracking</li>
                        </ul>

                        <h4>📈 Statistics Cards</h4>
                        <div class="eau-doc-grid">
                            <div class="eau-doc-stat">
                                <h4>Total Points</h4>
                                <p>Sum of all verified activity points for current year.</p>
                            </div>
                            <div class="eau-doc-stat">
                                <h4>Total Activities</h4>
                                <p>Count of all submitted activities.</p>
                            </div>
                            <div class="eau-doc-stat">
                                <h4>Verified</h4>
                                <p>Activities approved by administrators.</p>
                            </div>
                            <div class="eau-doc-stat">
                                <h4>Pending</h4>
                                <p>Activities awaiting approval.</p>
                            </div>
                        </div>

                        <h4>🔍 Search & Filter</h4>
                        <ul>
                            <li><strong>Search:</strong> By activity name or description</li>
                            <li><strong>Status Filter:</strong> All, Verified, Pending</li>
                            <li><strong>Category Filter:</strong> Dynamic list from user's activities</li>
                        </ul>

                        <h4>📋 Activities Table</h4>
                        <ul>
                            <li>Activity name with category badge</li>
                            <li>Date completed</li>
                            <li>Hours/Points earned</li>
                            <li>Verification status (badge)</li>
                            <li>Actions: View, Edit (pending only), Delete (pending only)</li>
                        </ul>

                        <h4>➕ Add New Activity</h4>
                        <p>Modal form with fields:</p>
                        <table class="eau-doc-table">
                            <thead>
                                <tr>
                                    <th>Field</th>
                                    <th>Type</th>
                                    <th>Required</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Activity Name</td>
                                    <td>Text</td>
                                    <td>Yes</td>
                                </tr>
                                <tr>
                                    <td>Category</td>
                                    <td>Select (with search)</td>
                                    <td>Yes</td>
                                </tr>
                                <tr>
                                    <td>Date Completed</td>
                                    <td>Date Picker</td>
                                    <td>Yes</td>
                                </tr>
                                <tr>
                                    <td>Hours</td>
                                    <td>Number (0.5 step)</td>
                                    <td>Yes</td>
                                </tr>
                                <tr>
                                    <td>Description</td>
                                    <td>WYSIWYG Editor</td>
                                    <td>No</td>
                                </tr>
                                <tr>
                                    <td>Proof/Evidence</td>
                                    <td>File Upload or URL</td>
                                    <td>No</td>
                                </tr>
                            </tbody>
                        </table>

                        <h4>📎 Proof Upload Component</h4>
                        <p>Uses the <code>Eau_Media_Upload</code> component with three options:</p>
                        <ul>
                            <li><strong>URL Tab:</strong> Enter external link to evidence</li>
                            <li><strong>Upload Tab:</strong> Drag & drop or browse for files (JPG, PNG, PDF)</li>
                            <li><strong>My Files Tab:</strong> Select from previously uploaded files</li>
                        </ul>

                        <h3>Workflow</h3>
                        <ol>
                            <li>Member submits activity → Status: <strong>Pending</strong></li>
                            <li>Admin reviews in Activities Management</li>
                            <li>Admin verifies → Status: <strong>Verified</strong>, points counted</li>
                            <li>Member sees updated progress</li>
                        </ol>

                        <h3>Permissions</h3>
                        <ul>
                            <li>All logged-in members can access their own CPDs</li>
                            <li>Members can only see their own activities</li>
                            <li>Edit/Delete only available for Pending activities</li>
                        </ul>

                        <h3>Technical Details</h3>
                        <ul>
                            <li><strong>Shortcode:</strong> <code>[eau_my_cpds]</code></li>
                            <li><strong>PHP Class:</strong> <code>class-eau-my-cpds.php</code></li>
                            <li><strong>JavaScript:</strong> <code>eau-my-cpds.js</code></li>
                            <li><strong>CSS:</strong> <code>eau-my-cpds.css</code></li>
                            <li><strong>AJAX Handler:</strong> <code>class-eau-my-cpds-ajax.php</code></li>
                        </ul>
                    </div>
                </div>

                <!-- My Profile Feature -->
                <div class="eau-doc-card">
                    <div class="eau-doc-card-header">
                        <h2>7. My Profile (Member Area)</h2>
                        <span class="eau-doc-badge eau-badge-v1">v1.40.0+</span>
                    </div>
                    <div class="eau-doc-card-body">
                        <h3>Overview</h3>
                        <p>Personal profile page where members can view and update their personal information, address, profile photo, and security settings.</p>

                        <h3>Page Layout</h3>

                        <h4>👤 Profile Header</h4>
                        <ul>
                            <li>Profile photo (clickable to change)</li>
                            <li>Full name (h1)</li>
                            <li>Email address</li>
                            <li>Member since date</li>
                            <li>User type badge (Super Admin, Admin, Institution Admin, Member)</li>
                            <li>Status badge (Active, Inactive, Pending)</li>
                        </ul>

                        <h4>📋 Information Sections</h4>

                        <h5>Personal Information</h5>
                        <ul>
                            <li>First Name</li>
                            <li>Last Name</li>
                            <li>Email</li>
                            <li>Phone (with international format)</li>
                            <li>Bio</li>
                        </ul>

                        <h5>Address</h5>
                        <ul>
                            <li>Street Address</li>
                            <li>City</li>
                            <li>Postcode</li>
                        </ul>

                        <h5>Account Information (Read-only)</h5>
                        <ul>
                            <li>Username</li>
                            <li>Member ID</li>
                            <li>User Type</li>
                            <li>Status</li>
                            <li>Institution (if applicable)</li>
                        </ul>

                        <h5>Security</h5>
                        <ul>
                            <li>Change Password button</li>
                        </ul>

                        <h3>Edit Modals</h3>

                        <h4>Edit Personal Information</h4>
                        <table class="eau-doc-table">
                            <thead>
                                <tr>
                                    <th>Field</th>
                                    <th>Type</th>
                                    <th>Editable</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>First Name</td>
                                    <td>Text</td>
                                    <td>Yes</td>
                                </tr>
                                <tr>
                                    <td>Last Name</td>
                                    <td>Text</td>
                                    <td>Yes</td>
                                </tr>
                                <tr>
                                    <td>Email</td>
                                    <td>Email</td>
                                    <td>Yes (validated)</td>
                                </tr>
                                <tr>
                                    <td>Phone</td>
                                    <td>International Phone Input</td>
                                    <td>Yes</td>
                                </tr>
                                <tr>
                                    <td>Bio</td>
                                    <td>Textarea</td>
                                    <td>Yes</td>
                                </tr>
                            </tbody>
                        </table>

                        <h4>Edit Address</h4>
                        <table class="eau-doc-table">
                            <thead>
                                <tr>
                                    <th>Field</th>
                                    <th>Type</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Street Address</td>
                                    <td>Text</td>
                                </tr>
                                <tr>
                                    <td>City</td>
                                    <td>Text</td>
                                </tr>
                                <tr>
                                    <td>Postcode</td>
                                    <td>Text</td>
                                </tr>
                            </tbody>
                        </table>

                        <h4>Change Password</h4>
                        <ul>
                            <li>Current Password (required)</li>
                            <li>New Password (min 8 characters)</li>
                            <li>Confirm New Password</li>
                            <li>Password visibility toggle (eye icon)</li>
                        </ul>

                        <h4>Update Profile Photo</h4>
                        <ul>
                            <li>Drag & drop or browse</li>
                            <li>Accepts: JPG, JPEG, PNG, GIF, WEBP</li>
                            <li>Max size: 5MB</li>
                            <li>Preview before saving</li>
                        </ul>

                        <h3>Special Components</h3>

                        <h4>📱 International Phone Input</h4>
                        <p>Uses <code>intl-tel-input</code> library:</p>
                        <ul>
                            <li>Country selector with flags</li>
                            <li>Auto-formats number based on country</li>
                            <li>Stores full international format (+XX XXXXXXXXX)</li>
                            <li>Default country: Australia</li>
                        </ul>

                        <h3>User Type Display</h3>
                        <table class="eau-doc-table">
                            <thead>
                                <tr>
                                    <th>mem_type Value</th>
                                    <th>Display Name</th>
                                    <th>Badge Color</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>superAdmin</td>
                                    <td>Super Admin</td>
                                    <td>Purple</td>
                                </tr>
                                <tr>
                                    <td>Admin</td>
                                    <td>Admin</td>
                                    <td>Blue</td>
                                </tr>
                                <tr>
                                    <td>institutionAdmin</td>
                                    <td>Institution Admin</td>
                                    <td>Indigo</td>
                                </tr>
                                <tr>
                                    <td>member</td>
                                    <td>Member</td>
                                    <td>Gray</td>
                                </tr>
                            </tbody>
                        </table>

                        <h3>Permissions</h3>
                        <ul>
                            <li>All logged-in users can access their profile</li>
                            <li>Users can only edit their own information</li>
                            <li>Username, Member ID, User Type, Status are read-only</li>
                        </ul>

                        <h3>Technical Details</h3>
                        <ul>
                            <li><strong>Shortcode:</strong> <code>[eau_my_profile]</code></li>
                            <li><strong>PHP Class:</strong> <code>class-eau-my-profile.php</code></li>
                            <li><strong>JavaScript:</strong> <code>eau-my-profile.js</code></li>
                            <li><strong>CSS:</strong> <code>eau-my-profile.css</code></li>
                            <li><strong>AJAX Handler:</strong> <code>class-eau-my-profile-ajax.php</code></li>
                            <li><strong>External Library:</strong> intl-tel-input v18.2.1</li>
                        </ul>
                    </div>
                </div>

                <!-- Design System -->
                <div class="eau-doc-card">
                    <div class="eau-doc-card-header">
                        <h2>8. Design System</h2>
                        <span class="eau-doc-badge eau-badge-info">Reference</span>
                    </div>
                    <div class="eau-doc-card-body">
                        <h3>Overview</h3>
                        <p>All features follow a consistent design system for unified user experience.</p>

                        <h3>Components</h3>
                        <ul>
                            <li><strong>Buttons:</strong> Primary, Secondary, Danger variants</li>
                            <li><strong>Forms:</strong> Text inputs, selects, textareas with validation</li>
                            <li><strong>Modals:</strong> Centered overlays for forms and confirmations</li>
                            <li><strong>Tables:</strong> Responsive data tables with sorting</li>
                            <li><strong>Cards:</strong> Container for grouped content</li>
                            <li><strong>Toasts:</strong> Success/error notifications</li>
                            <li><strong>Skeletons:</strong> Loading placeholders</li>
                            <li><strong>Media Upload:</strong> File upload with drag & drop</li>
                            <li><strong>WYSIWYG:</strong> Rich text editor (Quill.js)</li>
                        </ul>

                        <h3>Documentation</h3>
                        <p>Complete design system documentation available at:</p>
                        <code>docs/DESIGN-SYSTEM.md</code>
                    </div>
                </div>

            </div>
        </div>
        <?php
    }
}
