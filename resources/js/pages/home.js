const fileInput  = document.getElementById('heroFileInput');
const preview    = document.getElementById('hero-preview');
const hint       = document.getElementById('heroHint');
const uploadZone = document.getElementById('heroUploadZone');

if (fileInput && preview && hint && uploadZone) {
  fileInput.addEventListener('change', function () {
    const file = this.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = e => {
      preview.src = e.target.result;
      preview.style.display = 'block';
      hint.style.display = 'none';
      uploadZone.style.border = 'none';
    };
    reader.readAsDataURL(file);
  });
}
