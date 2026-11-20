document.addEventListener('DOMContentLoaded', () => {
  const picker = document.getElementById('studentPhotoFile');
  const dialog = document.getElementById('cropDialog');
  const canvas = document.getElementById('cropCanvas');
  const ctx = canvas?.getContext('2d');
  const zoom = document.getElementById('cropZoom');
  const hidden = document.getElementById('cropped_photo');
  const preview = document.getElementById('photoPreviewImage');
  const fallback = document.getElementById('photoPreviewFallback');
  if (!picker || !dialog || !canvas || !ctx || !zoom || !hidden) return;
  let image = null, scale = 1, baseScale = 1, offsetX = 0, offsetY = 0, dragging = false, startX = 0, startY = 0, movedX = 0, movedY = 0;
  const draw = () => {
    if (!image) return;
    const w = canvas.width, h = canvas.height;
    ctx.clearRect(0, 0, w, h); ctx.fillStyle = '#e9edf8'; ctx.fillRect(0, 0, w, h);
    const s = baseScale * scale, dw = image.width * s, dh = image.height * s;
    const x = (w - dw) / 2 + offsetX, y = (h - dh) / 2 + offsetY;
    ctx.save(); ctx.beginPath(); ctx.rect(0, 0, w, h); ctx.clip(); ctx.drawImage(image, x, y, dw, dh); ctx.restore();
    // Subtle square crop frame and corner guides.
    ctx.strokeStyle = 'rgba(255,255,255,.8)'; ctx.lineWidth = 3; ctx.strokeRect(2, 2, w - 4, h - 4);
  };
  const reset = () => { scale = 1; zoom.value = '1'; offsetX = 0; offsetY = 0; if (image) baseScale = Math.max(canvas.width / image.width, canvas.height / image.height); draw(); };
  picker.addEventListener('change', () => {
    const file = picker.files?.[0]; if (!file) return;
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) { alert('Please choose a JPG, PNG or WebP image.'); picker.value = ''; return; }
    if (file.size > 6 * 1024 * 1024) { alert('Please choose an image smaller than 6 MB.'); picker.value = ''; return; }
    const url = URL.createObjectURL(file); image = new Image();
    image.onload = () => { reset(); dialog.hidden = false; document.body.style.overflow = 'hidden'; URL.revokeObjectURL(url); };
    image.onerror = () => { alert('This image could not be opened. Please choose another.'); URL.revokeObjectURL(url); };
    image.src = url;
  });
  zoom.addEventListener('input', () => { const next = Number(zoom.value); const ratio = next / scale; offsetX *= ratio; offsetY *= ratio; scale = next; draw(); });
  document.getElementById('cropReset')?.addEventListener('click', reset);
  const close = () => { dialog.hidden = true; document.body.style.overflow = ''; };
  document.getElementById('cropCancel')?.addEventListener('click', close);
  dialog.addEventListener('click', e => { if (e.target === dialog) close(); });
  canvas.addEventListener('pointerdown', e => { dragging = true; canvas.setPointerCapture(e.pointerId); startX = e.clientX; startY = e.clientY; movedX = offsetX; movedY = offsetY; canvas.style.cursor = 'grabbing'; });
  canvas.addEventListener('pointermove', e => { if (!dragging) return; const rect = canvas.getBoundingClientRect(); offsetX = movedX + (e.clientX - startX) * canvas.width / rect.width; offsetY = movedY + (e.clientY - startY) * canvas.height / rect.height; draw(); });
  const stop = () => { dragging = false; canvas.style.cursor = 'grab'; };
  canvas.addEventListener('pointerup', stop); canvas.addEventListener('pointercancel', stop);
  document.getElementById('cropApply')?.addEventListener('click', () => {
    if (!image) return;
    hidden.value = canvas.toDataURL('image/jpeg', .9);
    preview.src = hidden.value; preview.hidden = false; if (fallback) fallback.hidden = true;
    close();
  });
  canvas.style.cursor = 'grab';
});
