/**
 * SCHOOL MANAGER - Modern JavaScript Utilities & UI Interactions
 */

document.addEventListener('DOMContentLoaded', function () {
  // 1. Mobile Sidebar Toggle
  const sidebar = document.getElementById('sidebar');
  const sidebarToggle = document.getElementById('sidebarToggle');
  const sidebarBackdrop = document.getElementById('sidebarBackdrop');

  if (sidebarToggle && sidebar) {
    sidebarToggle.addEventListener('click', function (e) {
      e.preventDefault();
      sidebar.classList.toggle('show');
      if (sidebarBackdrop) {
        sidebarBackdrop.classList.toggle('show');
      }
    });
  }

  if (sidebarBackdrop) {
    sidebarBackdrop.addEventListener('click', function () {
      if (sidebar) sidebar.classList.remove('show');
      sidebarBackdrop.classList.remove('show');
    });
  }

  // 2. Real-time Client-Side Table Search
  const searchInput = document.getElementById('tableSearch');
  if (searchInput) {
    searchInput.addEventListener('input', function () {
      const filter = this.value.toLowerCase().trim();
      const targetTable = document.querySelector('.table-searchable, .table-modern');
      if (!targetTable) return;

      const rows = targetTable.querySelectorAll('tbody tr');
      rows.forEach(function (row) {
        const text = row.textContent.toLowerCase();
        if (text.indexOf(filter) > -1) {
          row.style.display = '';
        } else {
          row.style.display = 'none';
        }
      });
    });
  }

  // 3. Generic Confirmation Modal for Deletion
  // Every delete button must supply these data attributes:
  //   data-action      = URL to POST to (e.g. /admin/students.php)
  //   data-id-field    = name of the hidden ID field (e.g. student_id)
  //   data-record-id   = the actual record ID value
  //   data-csrf        = current CSRF token value
  //   data-item-name   = human-readable label shown in modal
  //   data-message     = (optional) custom confirmation message
  const deleteModalEl = document.getElementById('deleteConfirmModal');
  if (deleteModalEl) {
    const deleteModal    = new bootstrap.Modal(deleteModalEl);
    const deleteForm     = document.getElementById('deleteConfirmForm');
    const deleteMessage  = document.getElementById('deleteConfirmMessage');
    const deleteItemName = document.getElementById('deleteConfirmItemName');
    const deleteItemId   = document.getElementById('deleteConfirmItemId');

    document.querySelectorAll('.btn-confirm-delete, [data-confirm-delete]').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();

        const actionUrl = this.getAttribute('data-action') || '';
        const idField   = this.getAttribute('data-id-field') || 'record_id';
        const recordId  = this.getAttribute('data-record-id') || '';
        const csrf      = this.getAttribute('data-csrf') || '';
        const itemName  = this.getAttribute('data-item-name') || 'this record';
        const submitAction = this.getAttribute('data-submit-action') || 'delete';

        if (!deleteForm) return;
        if (!actionUrl || !recordId) {
          window.alert('Unable to delete: the record endpoint or ID is missing.');
          return;
        }

        // 1. Set form action URL
        deleteForm.setAttribute('action', actionUrl);

        // 2. Set/create action=delete field
        let actField = deleteForm.querySelector('input[name="action"]');
        if (!actField) {
          actField = document.createElement('input');
          actField.type = 'hidden';
          actField.name = 'action';
          deleteForm.appendChild(actField);
        }
        actField.value = submitAction;

        // 3. Remove any previously injected record-id fields (from prior clicks)
        deleteForm.querySelectorAll('input[data-dynamic-id]').forEach(function(el) { el.remove(); });

        // 4. Create the record-id field dynamically
        const ridField = document.createElement('input');
        ridField.type = 'hidden';
        ridField.name = idField;
        ridField.value = recordId;
        ridField.setAttribute('data-dynamic-id', '1');
        deleteForm.appendChild(ridField);

        // 5. Update CSRF token if supplied by button
        if (csrf) {
          let csrfField = deleteForm.querySelector('input[name="csrf_token"]');
          if (!csrfField) {
            csrfField = document.createElement('input');
            csrfField.type = 'hidden';
            csrfField.name = 'csrf_token';
            deleteForm.appendChild(csrfField);
          }
          csrfField.value = csrf;
        }

        // 6. Update modal text
        if (deleteItemName) deleteItemName.textContent = itemName;
        if (deleteItemId) deleteItemId.textContent = recordId;
        if (deleteMessage) deleteMessage.textContent = 'Are you sure you want to delete this record? This action cannot be undone.';

        deleteModal.show();
      });
    });
  }

  // 4. Score to Letter Grade Auto-Calculator
  const scoreInput = document.getElementById('scoreInput');
  const letterGradeInput = document.getElementById('letterGradeInput');

  if (scoreInput && letterGradeInput) {
    scoreInput.addEventListener('input', function () {
      const val = parseFloat(this.value);
      if (isNaN(val)) {
        letterGradeInput.value = '';
        return;
      }
      let grade = 'F';
      if (val >= 93) grade = 'A';
      else if (val >= 90) grade = 'A-';
      else if (val >= 87) grade = 'B+';
      else if (val >= 83) grade = 'B';
      else if (val >= 80) grade = 'B-';
      else if (val >= 77) grade = 'C+';
      else if (val >= 73) grade = 'C';
      else if (val >= 70) grade = 'C-';
      else if (val >= 65) grade = 'D+';
      else if (val >= 60) grade = 'D';
      else grade = 'F';

      letterGradeInput.value = grade;
    });
  }

  // 5. Password Visibility Toggle
  document.querySelectorAll('.toggle-password').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const targetId = this.getAttribute('data-target');
      const input = document.getElementById(targetId);
      if (input) {
        const icon = this.querySelector('i');
        if (input.type === 'password') {
          input.type = 'text';
          if (icon) {
            icon.classList.remove('bi-eye');
            icon.classList.add('bi-eye-slash');
          }
        } else {
          input.type = 'password';
          if (icon) {
            icon.classList.remove('bi-eye-slash');
            icon.classList.add('bi-eye');
          }
        }
      }
    });
  });

  // 6. Quick Demo Credential Autofill (Login screen)
  document.querySelectorAll('.quick-demo-pill').forEach(function (pill) {
    pill.addEventListener('click', function () {
      const email = this.getAttribute('data-email');
      const pass = this.getAttribute('data-pass');
      const emailInput = document.getElementById('loginEmail');
      const passInput = document.getElementById('loginPassword');
      if (emailInput && passInput) {
        emailInput.value = email;
        passInput.value = pass;
        emailInput.focus();
      }
    });
  });

  // 7. Auto-dismiss alerts after 6 seconds
  const autoAlerts = document.querySelectorAll('.alert-dismissible');
  autoAlerts.forEach(function (alertEl) {
    setTimeout(function () {
      const bsAlert = bootstrap.Alert.getOrCreateInstance(alertEl);
      if (bsAlert) bsAlert.close();
    }, 6000);
  });
});
