function escapeHtml(value){
  return String(value ?? '')
    .replaceAll('&','&amp;')
    .replaceAll('<','&lt;')
    .replaceAll('>','&gt;')
    .replaceAll('"','&quot;')
    .replaceAll("'",'&#39;');
}

function statusDisplay(status){
  const normalized = String(status || '').toLowerCase();
  if(normalized === 'queued' || normalized === 'running') return 'Generating';
  if(normalized === 'succeeded') return 'Generated';
  if(normalized === 'failed') return 'Failed';
  return normalized ? normalized.charAt(0).toUpperCase() + normalized.slice(1) : 'Unknown';
}

function mediaViewUrl(item){
  return `/app/media.php?id=${encodeURIComponent(item.id)}`;
}

function mediaPreviewHtml(item){
  if(!item.output_path){
    return '<div class="history-thumb history-thumb-empty"><span>No preview yet</span></div>';
  }

  if(item.type === 'video'){
    return `<a class="history-thumb" href="${mediaViewUrl(item)}"><video src="${escapeHtml(item.output_path)}" muted playsinline preload="metadata"></video></a>`;
  }

  return `<a class="history-thumb" href="${mediaViewUrl(item)}"><img src="${escapeHtml(item.output_path)}" alt="Generated output preview"></a>`;
}

function historyDetailsHtml(item){
  const title = `<strong>${escapeHtml(item.type)}</strong> • ${escapeHtml(item.model_key)}`;
  const prompt = item.prompt ? `<span>${escapeHtml(item.prompt)}</span>` : '';
  const meta = `<small class="status-pill status-${escapeHtml(String(item.status || '').toLowerCase())}">${escapeHtml(statusDisplay(item.status))}</small>`;
  const errorText = item.error_message ? `<small class="muted">${escapeHtml(item.error_message)}</small>` : '';
  const linkStart = item.output_path ? `<a class="history-main-link" href="${mediaViewUrl(item)}">` : '<div class="history-main-link">';
  const linkEnd = item.output_path ? '</a>' : '</div>';

  return `${linkStart}${title}${meta}${prompt}${errorText}${linkEnd}`;
}

async function parseJsonResponse(response){
  const raw = await response.text();
  let parsed = null;
  try {
    parsed = raw ? JSON.parse(raw) : null;
  } catch (_e) {
    parsed = null;
  }
  return { raw, parsed };
}

function prettyError(response, parsed, raw){
  const parts = [`HTTP ${response.status}`];
  if(parsed && parsed.error){
    parts.push(String(parsed.error));
  } else if(raw) {
    parts.push(raw.slice(0, 500));
  }
  return parts.join(': ');
}

async function submitGeneration(e){
  e.preventDefault();
  const form = e.target;
  const formData = new FormData(form);
  const payload = {};
  formData.forEach((value, key)=>{
    if(key.endsWith('[]')){
      const normalized = key.slice(0, -2);
      payload[normalized] = payload[normalized] || [];
      payload[normalized].push(value);
    } else {
      payload[key] = value;
    }
  });
  if(!('extend_to_provider_max' in payload)){
    payload.extend_to_provider_max = 0;
  }

  const toDataUrl = (file) => new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => resolve(typeof reader.result === 'string' ? reader.result : '');
    reader.onerror = () => reject(new Error('Unable to read selected file.'));
    reader.readAsDataURL(file);
  });

  const referenceFile = formData.get('reference_media');
  const extendFile = formData.get('extend_media');

  if(referenceFile instanceof File && referenceFile.size > 0){
    payload.input_image = await toDataUrl(referenceFile);
  }

  if(extendFile instanceof File && extendFile.size > 0){
    const extendDataUrl = await toDataUrl(extendFile);
    if(extendFile.type.startsWith('video/')){
      payload.input_video = extendDataUrl;
    } else {
      payload.input_image = extendDataUrl;
    }
  }

  delete payload.reference_media;
  delete payload.extend_media;
  delete payload.extend_video;
  payload.duration_seconds = parseFloat(payload.duration_seconds || '5');
  payload.fps = parseInt(payload.fps || '24', 10);

  const statusBox = document.getElementById('statusBox');
  statusBox.textContent = 'Submitting generation request...';

  try {
    const res = await fetch('/api/generate.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
    const { parsed, raw } = await parseJsonResponse(res);
    if(!res.ok){
      throw new Error(prettyError(res, parsed, raw));
    }

    const generationId = parsed && parsed.id ? ` ID: ${parsed.id}` : '';
    statusBox.textContent = `Generation submitted.${generationId} Processing has started and will appear below once preview/output is available.`;
    await loadHistory();
  } catch (err) {
    statusBox.textContent = `Generation request failed: ${err.message}`;
  }
}

async function requestTick(){
  try {
    await fetch('/api/tick.php');
  } catch (_err) {
    // Non-fatal: history still renders latest known state.
  }
}

async function loadHistory(){
  await requestTick();
  const res = await fetch('/api/history.php');
  const data = await res.json();
  const box = document.getElementById('historyBox');
  if (!box) return;
  box.innerHTML = '';
  (data.items||[]).forEach(item=>{
    const div=document.createElement('div');
    div.className='card history-item';
    const canDownload = item.output_path && String(item.status || '').toLowerCase() === 'succeeded';
    div.innerHTML=`${mediaPreviewHtml(item)}<div class="history-content">${historyDetailsHtml(item)}<div class="history-actions">${canDownload?`<a class="btn btn-secondary" href="/api/download.php?id=${encodeURIComponent(item.id)}">Download</a>`:''}<button class="btn btn-danger js-delete-generation" type="button" data-id="${escapeHtml(item.id)}">Delete</button></div></div>`;
    box.appendChild(div);
  });
}

async function onDeleteGenerationClick(e){
  const button = e.target.closest('.js-delete-generation');
  if(!button) return;

  const id = button.getAttribute('data-id');
  if(!id) return;
  if(!window.confirm('Delete this generation from the gallery/history?')) return;

  button.disabled = true;
  try{
    const response = await fetch(`/api/delete.php?id=${encodeURIComponent(id)}`);
    if(!response.ok){
      throw new Error('Delete failed');
    }
  }catch(_err){
    button.disabled = false;
    window.alert('Could not delete this item right now. Please try again.');
    return;
  }

  const row = button.closest('.gallery-item, .history-item');
  if(row){
    row.remove();
  }else{
    await loadHistory();
  }
}


async function onToggleVisibilityClick(e){
  const button = e.target.closest('.js-toggle-visibility');
  if(!button) return;
  const id = button.getAttribute('data-id');
  if(!id) return;
  const response = await fetch('/api/toggle_visibility.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id})});
  if(!response.ok){ window.alert('Could not update visibility.'); return; }
  const data = await response.json();
  const isPublic = Number(data.is_public || 0) === 1;
  button.setAttribute('data-public', isPublic ? '1' : '0');
  button.textContent = isPublic ? '🔗 Public' : '🔒 Private';
}

function bindMobileNav(){
  const menuToggle = document.querySelector('.menu-toggle');
  const navLinks = document.querySelector('#nav-links');
  if (!menuToggle || !navLinks) return;

  const closeMenu = () => {
    navLinks.classList.remove('open');
    menuToggle.setAttribute('aria-expanded', 'false');
  };

  menuToggle.addEventListener('click', ()=>{
    const open = navLinks.classList.toggle('open');
    menuToggle.setAttribute('aria-expanded', String(open));
  });

  navLinks.querySelectorAll('a').forEach((link) => {
    link.addEventListener('click', closeMenu);
  });

  document.addEventListener('click', (event) => {
    if (!navLinks.classList.contains('open')) return;
    if (navLinks.contains(event.target) || menuToggle.contains(event.target)) return;
    closeMenu();
  });

  window.addEventListener('resize', () => {
    if (window.innerWidth > 700) {
      closeMenu();
    }
  });
}

function setupGeneratorTabs(form){
  const tabs = Array.from(form.querySelectorAll('[data-generator-tab]'));
  const modeInput = form.querySelector('input[name="generation_mode"]');
  const typeInput = form.querySelector('input[name="type"]');
  const modelSelect = form.querySelector('select[name="model_key"]');
  const promptInput = form.querySelector('textarea[name="prompt"]');
  const negativeInput = form.querySelector('textarea[name="negative_prompt"]');
  const sceneSelect = form.querySelector('#sceneSelect');
  const characterSelect = form.querySelector('#characterSelect');
  if(!tabs.length || !typeInput || !modelSelect || !promptInput || !negativeInput || !modeInput) return;

  const promptState = { image: { prompt: '', negative: '' }, video: { prompt: '', negative: '' } };
  const allModelOptions = Array.from(modelSelect.options).map((opt)=>opt.cloneNode(true));

  const refreshModelVisibility = (type) => {
    const prevValue = modelSelect.value;
    modelSelect.innerHTML = '';
    const options = allModelOptions
      .filter((opt) => (opt.getAttribute('data-model-type') || 'image') === type);
    options.forEach((opt)=>modelSelect.appendChild(opt.cloneNode(true)));
    if(options.find((opt)=>opt.value===prevValue)){
      modelSelect.value = prevValue;
    } else if(options[0]) {
      modelSelect.value = options[0].value;
    }
  };

  const refreshSceneVisibility = (type) => {
    if(!sceneSelect) return;
    Array.from(sceneSelect.options).forEach((opt)=>{
      if(!opt.value) return;
      opt.hidden = (opt.getAttribute('data-scene-type') || 'image') !== type;
    });
    if(sceneSelect.selectedOptions[0]?.hidden){
      sceneSelect.value = '';
    }
  };

  const setType = (nextType) => {
    const prevType = typeInput.value || 'image';
    promptState[prevType] = {
      prompt: promptInput.value,
      negative: negativeInput.value,
    };

    typeInput.value = nextType;
    tabs.forEach((tab) => {
      const active = tab.getAttribute('data-generator-tab') === nextType;
      tab.classList.toggle('is-active', active);
      tab.setAttribute('aria-selected', String(active));
    });

    promptInput.value = promptState[nextType].prompt || '';
    negativeInput.value = promptState[nextType].negative || '';
    refreshModelVisibility(nextType);
    refreshSceneVisibility(nextType);

    document.querySelectorAll('.row-image-only').forEach((row)=>row.classList.toggle('is-hidden', nextType !== 'image'));
    document.querySelectorAll('.row-video-only').forEach((row)=>row.classList.toggle('is-hidden', nextType !== 'video'));
  };

  const setGeneratorTab = (nextTab) => {
    const isExtend = nextTab === 'extend';
    modeInput.value = isExtend ? 'extend' : 'create';
    const tabType = nextTab === 'extend' ? 'video' : nextTab;
    setType(tabType);

    document.querySelectorAll('.row-extend-only').forEach((row)=>row.classList.toggle('is-hidden', !isExtend));
    document.querySelectorAll('.row-standard-media').forEach((row)=>row.classList.toggle('is-hidden', isExtend));

    document.querySelectorAll('#characterSelect, #sceneSelect, #partSelect').forEach((input)=>{
      const row = input.closest('.row');
      if(row){
        row.classList.toggle('is-hidden', isExtend);
      }
      if(isExtend){
        if(input.tagName === 'SELECT' && input.multiple){
          Array.from(input.options).forEach((opt)=>{ opt.selected = false; });
        }else{
          input.value = '';
        }
      }
    });

    const standardFileInput = form.querySelector('input[name="reference_media"]');
    const extendFileInput = form.querySelector('input[name="extend_media"]');
    if(standardFileInput && isExtend){ standardFileInput.value = ''; }
    if(extendFileInput && !isExtend){ extendFileInput.value = ''; }
  };

  if(characterSelect){
    characterSelect.addEventListener('change', ()=>{
      const max = parseInt(characterSelect.getAttribute('data-max-select') || '3', 10);
      const selected = Array.from(characterSelect.selectedOptions);
      if(selected.length > max){
        selected[selected.length - 1].selected = false;
        window.alert(`You can select up to ${max} characters.`);
      }
    });
  }

  tabs.forEach((tab)=>tab.addEventListener('click', ()=>setGeneratorTab(tab.getAttribute('data-generator-tab') || 'image')));
  setGeneratorTab((modeInput.value || 'create') === 'extend' ? 'extend' : (typeInput.value || 'image'));
}

document.addEventListener('DOMContentLoaded',()=>{
  bindMobileNav();
  document.body.addEventListener('click', onDeleteGenerationClick);
  document.body.addEventListener('click', onToggleVisibilityClick);

  const f=document.getElementById('generateForm');
  if(f){
    setupGeneratorTabs(f);
    f.addEventListener('submit',submitGeneration);
    loadHistory();
    setInterval(loadHistory, 8000);
  }
});
