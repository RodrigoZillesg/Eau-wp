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

                <!-- Duplicate Manager Feature -->
                <div class="eau-doc-card">
                    <div class="eau-doc-card-header">
                        <h2>3. Duplicate Manager</h2>
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

                <!-- Design System -->
                <div class="eau-doc-card">
                    <div class="eau-doc-card-header">
                        <h2>4. Design System</h2>
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
