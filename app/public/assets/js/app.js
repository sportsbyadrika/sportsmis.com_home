/* SportsMIS – Application JavaScript */

'use strict';

// ── Auto-dismiss flash alerts ─────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.alert.fade.show').forEach(function (el) {
    setTimeout(function () {
      var bs = bootstrap.Alert.getOrCreateInstance(el);
      if (bs) bs.close();
    }, 5000);
  });
});

// ── Confirm delete / reject forms ─────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('click', function (e) {
      if (!confirm(el.dataset.confirm)) e.preventDefault();
    });
  });
});

// ── Mobile number: digits only ────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('input[type=tel]').forEach(function (el) {
    el.addEventListener('input', function () {
      this.value = this.value.replace(/\D/g, '').slice(0, 10);
    });
  });
});

// ── Date validation: reg_date_to >= reg_date_from ─────────────────────────
document.addEventListener('DOMContentLoaded', function () {
  var fromFields = ['reg_date_from', 'event_date_from'];
  fromFields.forEach(function (fromId) {
    var fromEl = document.getElementById(fromId);
    var toId   = fromId.replace('_from', '_to');
    var toEl   = document.getElementById(toId);
    if (!fromEl || !toEl) return;
    fromEl.addEventListener('change', function () {
      if (toEl.value && toEl.value < fromEl.value) toEl.value = fromEl.value;
      toEl.min = fromEl.value;
    });
  });
});

// ── Active nav highlight (fallback for server-side class) ─────────────────
document.addEventListener('DOMContentLoaded', function () {
  var path = window.location.pathname;
  document.querySelectorAll('.nav-link').forEach(function (link) {
    var href = link.getAttribute('href');
    if (href && href !== '/' && path.startsWith(href)) {
      link.classList.add('active');
    }
  });
});

// ── File input size/type client-side guard ────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('input[type=file]').forEach(function (input) {
    input.addEventListener('change', function () {
      var file = this.files[0];
      if (!file) return;
      var maxMb = this.dataset.maxMb ? parseFloat(this.dataset.maxMb) : 5;
      if (file.size > maxMb * 1024 * 1024) {
        alert('File is too large. Maximum ' + maxMb + ' MB allowed.');
        this.value = '';
      }
    });
  });
});

// ── Image preview for file inputs ─────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('input[type=file][data-preview]').forEach(function (input) {
    input.addEventListener('change', function () {
      var previewId = this.dataset.preview;
      var preview   = document.getElementById(previewId);
      if (!preview || !this.files[0]) return;
      var reader = new FileReader();
      reader.onload = function (e) { preview.src = e.target.result; };
      reader.readAsDataURL(this.files[0]);
    });
  });
});

// ── Tooltip init ──────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
  [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    .forEach(function (el) { new bootstrap.Tooltip(el); });
});
