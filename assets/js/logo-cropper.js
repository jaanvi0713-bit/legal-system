(function () {
  'use strict';
  var block = document.getElementById('logoFaviconBlock');
  var modal = document.getElementById('logoCropModal');
  if (!block || !modal) return;

  var fileInput = document.getElementById('logoFileInput');
  var fileName = document.getElementById('logoFileName');
  var editBtn = document.getElementById('logoEditBtn');
  var removeBtn = document.getElementById('logoRemoveBtn');
  var appears = document.getElementById('logoAppears');
  var prevSidebar = document.getElementById('prevSidebar');
  var prevLogin = document.getElementById('prevLogin');
  var logoData = document.getElementById('companyLogoData');
  var removeLogo = document.getElementById('removeCompanyLogo');

  var stage = document.getElementById('logoCropStage');
  var img = document.getElementById('logoCropImg');
  var frame = document.getElementById('logoCropFrame');
  var lcpSidebar = document.getElementById('lcpSidebar');
  var lcpLogin = document.getElementById('lcpLogin');
  var applyBtn = document.getElementById('logoCropApply');
  var tabs = modal.querySelectorAll('.logo-crop-tab');

  var favInput = document.getElementById('faviconFileInput');
  var favName = document.getElementById('faviconFileName');
  var favEditBtn = document.getElementById('faviconEditBtn');
  var favRemoveBtn = document.getElementById('faviconRemoveBtn');
  var favNote = document.getElementById('faviconNote');
  var faviconData = document.getElementById('companyFaviconData');
  var cropTitle = document.getElementById('logoCropTitle');
  var removeFavicon = document.getElementById('removeCompanyFavicon');
  var noFaviconText = favNote ? (favNote.querySelector('.muted') ? favNote.querySelector('.muted').textContent : '') : '';

  var currentTarget = 'logo';

  var natW = 0, natH = 0, scale = 1, posX = 0, posY = 0;
  var fx = 0, fy = 0, fw = 0, fh = 0;
  var mode = 'square';
  var rafId = null;

  function stageSize() {
    var r = stage.getBoundingClientRect();
    return { w: r.width, h: r.height };
  }

  function clampFrame() {
    var imgW = natW * scale, imgH = natH * scale;
    var minX = posX, minY = posY, maxX = posX + imgW, maxY = posY + imgH;
    if (fw > imgW) fw = imgW;
    if (fh > imgH) fh = imgH;
    if (mode === 'square') { var m = Math.min(fw, fh); fw = m; fh = m; }
    if (fx < minX) fx = minX;
    if (fy < minY) fy = minY;
    if (fx + fw > maxX) fx = maxX - fw;
    if (fy + fh > maxY) fy = maxY - fh;
  }

  function applyTransforms() {
    img.style.width = (natW * scale) + 'px';
    img.style.height = (natH * scale) + 'px';
    img.style.left = posX + 'px';
    img.style.top = posY + 'px';
    frame.style.left = fx + 'px';
    frame.style.top = fy + 'px';
    frame.style.width = fw + 'px';
    frame.style.height = fh + 'px';
    schedulePreview();
  }

  function schedulePreview() {
    if (rafId) return;
    rafId = requestAnimationFrame(function () {
      rafId = null;
      var url = cropDataUrl();
      if (url) {
        if (lcpSidebar) lcpSidebar.src = url;
        if (lcpLogin) lcpLogin.src = url;
      }
    });
  }

  function cropDataUrl() {
    if (!natW || !natH) return '';
    var sx = (fx - posX) / scale;
    var sy = (fy - posY) / scale;
    var sw = fw / scale;
    var sh = fh / scale;
    sx = Math.max(0, Math.min(natW, sx));
    sy = Math.max(0, Math.min(natH, sy));
    sw = Math.max(1, Math.min(natW - sx, sw));
    sh = Math.max(1, Math.min(natH - sy, sh));
    var outMax = 512;
    var f = Math.min(1, outMax / Math.max(sw, sh));
    var cw = Math.max(1, Math.round(sw * f));
    var ch = Math.max(1, Math.round(sh * f));
    var canvas = document.createElement('canvas');
    canvas.width = cw;
    canvas.height = ch;
    var ctx = canvas.getContext('2d');
    try {
      ctx.drawImage(img, sx, sy, sw, sh, 0, 0, cw, ch);
      return canvas.toDataURL('image/png');
    } catch (e) {
      return '';
    }
  }

  function initCrop() {
    var s = stageSize();
    if (!s.w || !s.h) { requestAnimationFrame(initCrop); return; }
    // "contain" the whole image inside the stage; it never moves after this.
    scale = Math.min(s.w / natW, s.h / natH);
    var imgW = natW * scale, imgH = natH * scale;
    posX = (s.w - imgW) / 2;
    posY = (s.h - imgH) / 2;
    if (mode === 'free') {
      fw = imgW * 0.9; fh = imgH * 0.9;
    } else {
      var side = Math.min(imgW, imgH) * 0.85;
      fw = side; fh = side;
    }
    fx = posX + (imgW - fw) / 2;
    fy = posY + (imgH - fh) / 2;
    applyTransforms();
  }

  // Drag the crop frame (the image stays fixed)
  var dragging = false, startX = 0, startY = 0, startFx = 0, startFy = 0;
  frame.addEventListener('pointerdown', function (e) {
    if (e.target.classList.contains('lcf-handle')) return;
    dragging = true;
    startX = e.clientX; startY = e.clientY; startFx = fx; startFy = fy;
    frame.setPointerCapture(e.pointerId);
    e.preventDefault();
  });
  frame.addEventListener('pointermove', function (e) {
    if (!dragging) return;
    fx = startFx + (e.clientX - startX);
    fy = startFy + (e.clientY - startY);
    clampFrame();
    applyTransforms();
  });
  function endDrag(e) { if (dragging) { dragging = false; try { frame.releasePointerCapture(e.pointerId); } catch (x) {} } }
  frame.addEventListener('pointerup', endDrag);
  frame.addEventListener('pointercancel', endDrag);

  // Resize frame with handles
  var resizing = false, rHandle = '', rStartX = 0, rStartY = 0, rFx = 0, rFy = 0, rFw = 0, rFh = 0;
  frame.querySelectorAll('.lcf-handle').forEach(function (h) {
    h.addEventListener('pointerdown', function (e) {
      resizing = true;
      rHandle = h.getAttribute('data-handle');
      rStartX = e.clientX; rStartY = e.clientY;
      rFx = fx; rFy = fy; rFw = fw; rFh = fh;
      h.setPointerCapture(e.pointerId);
      e.preventDefault();
      e.stopPropagation();
    });
    h.addEventListener('pointermove', function (e) {
      if (!resizing) return;
      var dx = e.clientX - rStartX;
      var dy = e.clientY - rStartY;
      var nx = rFx, ny = rFy, nw = rFw, nh = rFh;
      var minSize = 40;
      if (mode === 'square') {
        var d = (rHandle === 'nw' || rHandle === 'sw') ? -dx : dx;
        var d2 = (rHandle === 'nw' || rHandle === 'ne') ? -dy : dy;
        var delta = Math.abs(d) > Math.abs(d2) ? d : d2;
        var newSize = Math.max(minSize, rFw + delta);
        if (rHandle === 'nw') { nx = rFx + (rFw - newSize); ny = rFy + (rFh - newSize); }
        else if (rHandle === 'ne') { ny = rFy + (rFh - newSize); }
        else if (rHandle === 'sw') { nx = rFx + (rFw - newSize); }
        nw = newSize; nh = newSize;
      } else {
        if (rHandle.indexOf('w') >= 0) { nx = rFx + dx; nw = rFw - dx; }
        if (rHandle.indexOf('e') >= 0) { nw = rFw + dx; }
        if (rHandle.indexOf('n') >= 0) { ny = rFy + dy; nh = rFh - dy; }
        if (rHandle.indexOf('s') >= 0) { nh = rFh + dy; }
        if (nw < minSize) { if (rHandle.indexOf('w') >= 0) nx = rFx + rFw - minSize; nw = minSize; }
        if (nh < minSize) { if (rHandle.indexOf('n') >= 0) ny = rFy + rFh - minSize; nh = minSize; }
      }
      // keep the crop within the (fixed) image bounds
      var minX = posX, minY = posY, maxX = posX + natW * scale, maxY = posY + natH * scale;
      if (nx < minX) { nw += nx - minX; nx = minX; }
      if (ny < minY) { nh += ny - minY; ny = minY; }
      if (nx + nw > maxX) { nw = maxX - nx; }
      if (ny + nh > maxY) { nh = maxY - ny; }
      if (mode === 'square') { var m = Math.min(nw, nh); nw = m; nh = m; }
      if (nw < minSize) nw = minSize;
      if (nh < minSize) nh = minSize;
      fx = nx; fy = ny; fw = nw; fh = nh;
      applyTransforms();
    });
    h.addEventListener('pointerup', function (e) { resizing = false; try { h.releasePointerCapture(e.pointerId); } catch (x) {} });
    h.addEventListener('pointercancel', function () { resizing = false; });
  });

  tabs.forEach(function (t) {
    t.addEventListener('click', function () {
      tabs.forEach(function (x) { x.classList.remove('is-active'); });
      t.classList.add('is-active');
      mode = t.getAttribute('data-mode');
      frame.classList.toggle('is-free', mode === 'free');
      initCrop();
    });
  });

  function openModal(src, target) {
    currentTarget = target || 'logo';
    if (cropTitle) {
      var t = cropTitle.getAttribute('data-' + currentTarget);
      if (t) cropTitle.textContent = t;
    }
    // Show the modal first so the stage has its real on-screen size
    // before we measure it, then load the image.
    modal.hidden = false;
    modal.classList.add('is-open');
    document.body.style.overflow = 'hidden';
    var ready = function () {
      natW = img.naturalWidth; natH = img.naturalHeight;
      if (!natW || !natH) return;
      // Wait for layout to settle so stageSize() is accurate.
      requestAnimationFrame(function () {
        requestAnimationFrame(initCrop);
      });
    };
    img.onload = ready;
    img.onerror = null;
    img.src = src;
    if (img.complete && img.naturalWidth) {
      ready();
    }
  }

  function closeModal() {
    modal.classList.remove('is-open');
    modal.hidden = true;
    document.body.style.overflow = '';
  }

  modal.querySelectorAll('[data-close]').forEach(function (el) {
    el.addEventListener('click', closeModal);
  });

  function readFileToModal(input, nameEl, target) {
    var f = input.files && input.files[0];
    if (!f) return;
    if (nameEl) nameEl.textContent = f.name;
    var reader = new FileReader();
    reader.onload = function (ev) { openModal(ev.target.result, target); };
    reader.readAsDataURL(f);
  }

  if (fileInput) {
    fileInput.addEventListener('change', function () { readFileToModal(fileInput, fileName, 'logo'); });
  }
  if (favInput) {
    favInput.addEventListener('change', function () { readFileToModal(favInput, favName, 'favicon'); });
  }

  if (editBtn) {
    editBtn.addEventListener('click', function () {
      var src = (logoData && logoData.value) ? logoData.value : block.getAttribute('data-logo-url');
      if (src) openModal(src, 'logo');
    });
  }
  if (favEditBtn) {
    favEditBtn.addEventListener('click', function () {
      var src = (faviconData && faviconData.value) ? faviconData.value : block.getAttribute('data-favicon-url');
      if (src) openModal(src, 'favicon');
    });
  }

  function applyLogo(url) {
    if (logoData) logoData.value = url;
    if (removeLogo) removeLogo.value = '0';
    if (prevSidebar) prevSidebar.src = url;
    if (prevLogin) prevLogin.src = url;
    if (appears) appears.hidden = false;
    if (editBtn) editBtn.hidden = false;
    if (removeBtn) removeBtn.hidden = false;
  }

  function applyFavicon(url) {
    if (faviconData) faviconData.value = url;
    if (removeFavicon) removeFavicon.value = '0';
    if (favNote) favNote.innerHTML = '<img src="' + url + '" alt="" class="favicon-note-img">';
    if (favEditBtn) favEditBtn.hidden = false;
    if (favRemoveBtn) favRemoveBtn.hidden = false;
  }

  if (applyBtn) {
    applyBtn.addEventListener('click', function () {
      var url = cropDataUrl();
      if (!url) { closeModal(); return; }
      if (currentTarget === 'favicon') { applyFavicon(url); } else { applyLogo(url); }
      closeModal();
    });
  }

  if (removeBtn) {
    removeBtn.addEventListener('click', function () {
      if (removeLogo) removeLogo.value = '1';
      if (logoData) logoData.value = '';
      if (appears) appears.hidden = true;
      removeBtn.hidden = true;
      if (editBtn) editBtn.hidden = true;
      if (fileInput) fileInput.value = '';
      if (fileName) fileName.textContent = fileName.getAttribute('data-empty') || fileName.textContent;
    });
  }
  if (favRemoveBtn) {
    favRemoveBtn.addEventListener('click', function () {
      if (removeFavicon) removeFavicon.value = '1';
      if (faviconData) faviconData.value = '';
      if (favInput) favInput.value = '';
      if (favName) favName.textContent = favName.getAttribute('data-empty') || favName.textContent;
      if (favNote) favNote.innerHTML = '<span class="muted">' + noFaviconText + '</span>';
      favRemoveBtn.hidden = true;
      if (favEditBtn) favEditBtn.hidden = true;
    });
  }
})();
