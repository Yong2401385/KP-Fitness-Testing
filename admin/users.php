<?php
define('PAGE_TITLE', 'User Management');
require_once '../includes/config.php';
require_admin();

$feedback = [];

// --- Handle Form Submissions ---

// Handle trainer creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_trainer'])) {
    validate_csrf_token($_POST['csrf_token']);
    $fullName = sanitize_input($_POST['fullName']);
    $email = sanitize_input($_POST['email']);
    $password = $_POST['password'];
    $gender = sanitize_input($_POST['gender']);
    
    // Basic Validation
    if (empty($fullName) || empty($email) || empty($password) || empty($gender)) {
        $feedback = ['type' => 'danger', 'message' => 'Please fill in all required fields.'];
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $feedback = ['type' => 'danger', 'message' => 'Invalid email format.'];
    } else {
        try {
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT UserID FROM users WHERE Email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $feedback = ['type' => 'danger', 'message' => 'An account with this email already exists.'];
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (FullName, Email, Password, Role, Gender) VALUES (?, ?, ?, 'trainer', ?)");
                if ($stmt->execute([$fullName, $email, $hashedPassword, $gender])) {
                    $feedback = ['type' => 'success', 'message' => 'Trainer account created successfully.'];
                } else {
                    $feedback = ['type' => 'danger', 'message' => 'Failed to create trainer account.'];
                }
            }
        } catch (PDOException $e) {
            $feedback = ['type' => 'danger', 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
}

// Handle user deactivation/reactivation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['deactivate_user']) || isset($_POST['reactivate_user']))) {
    validate_csrf_token($_POST['csrf_token']);
    $userId = intval($_POST['userId']);
    $newStatus = isset($_POST['deactivate_user']) ? 0 : 1;
    $action = $newStatus === 0 ? 'deactivated' : 'reactivated';

    try {
        $stmt = $pdo->prepare("UPDATE users SET IsActive = ? WHERE UserID = ? AND Role != 'admin'");
        if ($stmt->execute([$newStatus, $userId])) {
            $feedback = ['type' => 'success', 'message' => "User account has been $action."];
        } else {
            $feedback = ['type' => 'danger', 'message' => "Failed to $action user account."];
        }
    } catch (PDOException $e) {
        $feedback = ['type' => 'danger', 'message' => 'Database error: ' . $e->getMessage()];
    }
}

// Handle user deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    validate_csrf_token($_POST['csrf_token']);
    $userId = intval($_POST['userId']);
    
    try {
        // Check if user is admin
        $stmt = $pdo->prepare("SELECT Role FROM users WHERE UserID = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if ($user && $user['Role'] === 'admin') {
            $feedback = ['type' => 'danger', 'message' => 'Cannot delete admin accounts.'];
        } else {
            $stmt = $pdo->prepare("DELETE FROM users WHERE UserID = ? AND Role != 'admin'");
            if ($stmt->execute([$userId])) {
                $feedback = ['type' => 'success', 'message' => 'User account deleted successfully.'];
            }
        }
    } catch (PDOException $e) {
        $feedback = ['type' => 'danger', 'message' => 'Database error: ' . $e->getMessage()];
    }
}


// --- Fetch Data for Display ---
try {
    // Fetch all users including admins
    $stmt = $pdo->prepare("SELECT UserID, FullName, Email, Role, IsActive, Gender FROM users ORDER BY Role, FullName");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $feedback = ['type' => 'danger', 'message' => 'Could not fetch user data: ' . $e->getMessage()];
    $users = [];
}

include 'includes/admin_header.php';
?>

<style>
    .user-folder-card {
        cursor: pointer;
        transition: transform 0.2s, box-shadow 0.2s;
        border: 2px solid transparent;
    }
    .user-folder-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        border-color: var(--primary-color);
    }
    .folder-icon {
        font-size: 3rem;
        color: var(--primary-color);
        margin-bottom: 1rem;
    }
    .folder-title-style {
        background-color: var(--primary-color);
        color: var(--text-light) !important; /* Ensure text is white */
        padding: 0.25rem 0.75rem;
        border-radius: 0.3rem; /* Slightly rounded corners */
        display: inline-block; /* To allow background to wrap content */
        margin-bottom: 0.5rem; /* Space below the title */
    }
    .modal-header {
        background-color: var(--dark-bg);
        border-bottom: 1px solid var(--border-color);
    }
    .modal-header .modal-title {
        background-color: var(--primary-color);
        color: var(--text-light);
        padding: 0.25rem 0.75rem;
        border-radius: 0.3rem;
    }
    .modal-content {
        background-color: var(--light-bg);
        color: var(--text-light);
        border: 1px solid var(--border-color);
    }
    .modal-close-btn {
        filter: invert(1);
    }
</style>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">User Management</h1>
</div>

<?php if (!empty($feedback)): ?>
    <div class="alert alert-<?php echo $feedback['type']; ?> alert-dismissible fade show" role="alert">
        <?php echo $feedback['message']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Create Trainer Form -->
<div class="mb-4">
    <h3 class="mb-3">Create New Trainer</h3>
    <form action="users.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
        <div class="row g-3">
            <div class="col-md-3">
                <label for="fullName" class="form-label">Full Name</label>
                <input type="text" class="form-control" id="fullName" name="fullName" required>
            </div>
            <div class="col-md-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>
            <div class="col-md-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <div class="col-md-3">
                <label for="gender" class="form-label">Gender</label>
                <select class="form-select" id="gender" name="gender" required>
                    <option value="">Select...</option>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            <div class="col-12">
                <button type="submit" name="create_trainer" class="btn btn-primary">Create Trainer</button>
            </div>
        </div>
    </form>
</div>

<!-- Users Folders -->
<div class="mb-4">
    <h3 class="mb-3">All Users</h3>
    <div class="row">
        <!-- Admin Folder -->
        <div class="col-md-4 mb-3">
            <div class="card user-folder-card h-100 text-center p-4" onclick="openUserModal('admin')">
                <div class="folder-icon"><i class="fas fa-user-shield"></i></div>
                <h4 class="folder-title-style">Administrators</h4>
                <p class="text-muted">Manage system admins</p>
                <span class="badge bg-secondary"><?php echo count(array_filter($users, fn($u) => $u['Role'] === 'admin')); ?> Users</span>
            </div>
        </div>
        <!-- Trainer Folder -->
        <div class="col-md-4 mb-3">
            <div class="card user-folder-card h-100 text-center p-4" onclick="openUserModal('trainer')">
                <div class="folder-icon"><i class="fas fa-user-tie"></i></div>
                <h4 class="folder-title-style">Trainers</h4>
                <p class="text-muted">Manage fitness trainers</p>
                <span class="badge bg-secondary"><?php echo count(array_filter($users, fn($u) => $u['Role'] === 'trainer')); ?> Users</span>
            </div>
        </div>
        <!-- Client Folder -->
        <div class="col-md-4 mb-3">
            <div class="card user-folder-card h-100 text-center p-4" onclick="openUserModal('client')">
                <div class="folder-icon"><i class="fas fa-user"></i></div>
                <h4 class="folder-title-style">Clients</h4>
                <p class="text-muted">Manage gym members</p>
                <span class="badge bg-secondary"><?php echo count(array_filter($users, fn($u) => $u['Role'] === 'client')); ?> Users</span>
            </div>
        </div>
    </div>
</div>

<!-- User List Modal -->
<div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-capitalize" id="userModalTitle">Users</h5>
                <button type="button" class="btn-close modal-close-btn" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Search and Filter Controls -->
                <div class="row g-2 mb-3">
                    <div class="col-md-4">
                        <input type="text" id="userSearchInput" class="form-control" placeholder="Search by name or email...">
                    </div>
                    <div class="col-md-4">
                        <select id="userStatusFilter" class="form-select">
                            <option value="all">All Status</option>
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <select id="userGenderFilter" class="form-select">
                            <option value="all">All Genders</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-striped table-hover table-dark mb-0">
                        <thead>
                            <tr>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th id="genderColHeader">Gender</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="userTableBody">
                            <!-- Populated by JS -->
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination Controls -->
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <div id="paginationInfo" class="text-muted"></div>
                    <nav aria-label="User list pagination">
                        <ul class="pagination mb-0">
                            <li class="page-item">
                                <button class="page-link" id="prevPageBtn" onclick="changePage(-1)">Previous</button>
                            </li>
                            <li class="page-item disabled">
                                <span class="page-link" id="pageIndicator">1</span>
                            </li>
                            <li class="page-item">
                                <button class="page-link" id="nextPageBtn" onclick="changePage(1)">Next</button>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    const allUsers = <?php echo json_encode($users); ?>;
    const csrfToken = '<?php echo get_csrf_token(); ?>';
    
    // State
    let currentRole = '';
    let currentPage = 1;
    const itemsPerPage = 20;
    let filteredUsers = [];

    function openUserModal(role) {
        currentRole = role;
        
        // Reset state
        currentPage = 1;
        document.getElementById('userSearchInput').value = '';
        document.getElementById('userStatusFilter').value = 'all';
        document.getElementById('userGenderFilter').value = 'all';
        
        // Show/Hide Gender Column based on role (Admin usually doesn't need it, but request said Trainer/Client)
        // Request said: 'pop-up display should also display "gender" for "Trainer" and "Client"'
        const showGender = (role === 'trainer' || role === 'client');
        document.getElementById('genderColHeader').style.display = showGender ? 'table-cell' : 'none';
        document.getElementById('userGenderFilter').parentElement.style.display = showGender ? 'block' : 'none';
        
        // Update Modal Title
        document.getElementById('userModalTitle').textContent = role + 's';
        
        // Initial Render
        filterAndRender();
        
        // Show Modal
        const modal = new bootstrap.Modal(document.getElementById('userModal'));
        modal.show();
    }

    // Filter Logic
    function filterAndRender() {
        const searchTerm = document.getElementById('userSearchInput').value.toLowerCase();
        const statusFilter = document.getElementById('userStatusFilter').value;
        const genderFilter = document.getElementById('userGenderFilter').value;

        // Filter master list
        filteredUsers = allUsers.filter(user => {
            // Role filter
            if (user.Role !== currentRole) return false;
            
            // Search filter
            const matchesSearch = user.FullName.toLowerCase().includes(searchTerm) || 
                                  user.Email.toLowerCase().includes(searchTerm);
            if (!matchesSearch) return false;

            // Status filter
            if (statusFilter !== 'all') {
                if (user.IsActive != statusFilter) return false;
            }

            // Gender filter
            if (genderFilter !== 'all') {
                 if ((user.Gender || '') !== genderFilter) return false;
            }

            return true;
        });

        // Reset to page 1 on filter change logic handled by callers or reset manually
        // But if this is called from pagination, we shouldn't reset. 
        // We will reset page only when search/filter changes.
        
        renderTable();
    }

    // Event Listeners for Filters
    document.getElementById('userSearchInput').addEventListener('input', () => {
        currentPage = 1;
        filterAndRender();
    });

    document.getElementById('userStatusFilter').addEventListener('change', () => {
        currentPage = 1;
        filterAndRender();
    });
    
    document.getElementById('userGenderFilter').addEventListener('change', () => {
        currentPage = 1;
        filterAndRender();
    });


    // Pagination Logic
    function changePage(delta) {
        const maxPage = Math.ceil(filteredUsers.length / itemsPerPage) || 1;
        const newPage = currentPage + delta;
        
        if (newPage >= 1 && newPage <= maxPage) {
            currentPage = newPage;
            renderTable();
        }
    }

    function renderTable() {
        const tbody = document.getElementById('userTableBody');
        tbody.innerHTML = '';
        
        if (filteredUsers.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No users found.</td></tr>';
            updatePaginationUI(0);
            return;
        }

        // Slice data for pagination
        const startIndex = (currentPage - 1) * itemsPerPage;
        const endIndex = startIndex + itemsPerPage;
        const pageData = filteredUsers.slice(startIndex, endIndex);

        const showGender = (currentRole === 'trainer' || currentRole === 'client');

        pageData.forEach(user => {
            const tr = document.createElement('tr');
            
            // Status Badge logic
            const statusBadge = user.IsActive == 1 
                ? '<span class="badge bg-success">Active</span>' 
                : '<span class="badge bg-danger">Inactive</span>';
            
            // Action Buttons logic
            let actionButtons = '';
            if (user.Role !== 'admin') { 
                const actionName = user.IsActive == 1 ? 'deactivate_user' : 'reactivate_user';
                const actionBtnClass = user.IsActive == 1 ? 'btn-danger' : 'btn-success';
                const actionBtnText = user.IsActive == 1 ? 'Deactivate' : 'Reactivate';
                
                actionButtons = `
                    <form action="users.php" method="POST" class="d-inline">
                        <input type="hidden" name="csrf_token" value="${csrfToken}">
                        <input type="hidden" name="userId" value="${user.UserID}">
                        <button type="submit" name="${actionName}" class="btn ${actionBtnClass} btn-sm">${actionBtnText}</button>
                `;
                
                // Only allow deletion for non-client/non-admin roles (e.g. Trainers) if needed, 
                // OR as per request: just remove for client. 
                // The prompt says "remove the 'Delete' button for Client". 
                // So if role is NOT client, show delete.
                if (user.Role !== 'client' && user.Role !== 'trainer') {
                     actionButtons += `<button type="submit" name="delete_user" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure?');">Delete</button>`;
                }
                
                actionButtons += `</form>`;
            } else {
                actionButtons = '<span class="text-muted fst-italic">Protected</span>';
            }

            const genderCell = showGender ? `<td>${escapeHtml(user.Gender || '-')}</td>` : '<td style="display:none"></td>';

            tr.innerHTML = `
                <td>${escapeHtml(user.FullName)}</td>
                <td>${escapeHtml(user.Email)}</td>
                ${genderCell}
                <td>${statusBadge}</td>
                <td>${actionButtons}</td>
            `;
            tbody.appendChild(tr);
        });

        updatePaginationUI(filteredUsers.length);
    }

    function updatePaginationUI(totalItems) {
        const maxPage = Math.ceil(totalItems / itemsPerPage) || 1;
        const startItem = totalItems === 0 ? 0 : (currentPage - 1) * itemsPerPage + 1;
        const endItem = Math.min(currentPage * itemsPerPage, totalItems);

        document.getElementById('paginationInfo').textContent = `Showing ${startItem}-${endItem} of ${totalItems}`;
        document.getElementById('pageIndicator').textContent = `${currentPage} / ${maxPage}`;
        
        document.getElementById('prevPageBtn').parentElement.classList.toggle('disabled', currentPage === 1);
        document.getElementById('nextPageBtn').parentElement.classList.toggle('disabled', currentPage === maxPage);
    }

    function escapeHtml(text) {
        if (!text) return '';
        return text
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
</script>

<?php include 'includes/admin_footer.php'; ?>
