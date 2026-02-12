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
  const payload = Object.fromEntries(new FormData(form).entries());
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

    statusBox.textContent = JSON.stringify(parsed || { ok: true }, null, 2);
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

function bindMobileNav(){
  const menuToggle = document.querySelector('.menu-toggle');
  const navLinks = document.querySelector('#nav-links');
  if (!menuToggle || !navLinks) return;

  menuToggle.addEventListener('click', ()=>{
    const open = navLinks.classList.toggle('open');
    menuToggle.setAttribute('aria-expanded', String(open));
  });
}

function setupGeneratorTabs(form){
  const tabs = Array.from(form.querySelectorAll('[data-type-tab]'));
  const typeInput = form.querySelector('input[name="type"]');
  const modelSelect = form.querySelector('select[name="model_key"]');
  const promptInput = form.querySelector('textarea[name="prompt"]');
  const negativeInput = form.querySelector('textarea[name="negative_prompt"]');
  if(!tabs.length || !typeInput || !modelSelect || !promptInput || !negativeInput) return;

  const promptState = { image: { prompt: '', negative: '' }, video: { prompt: '', negative: '' } };

  const refreshModelVisibility = (type) => {
    const options = Array.from(modelSelect.options);
    let firstVisibleValue = '';
    options.forEach((opt) => {
      const show = (opt.getAttribute('data-model-type') || 'image') === type;
      opt.hidden = !show;
      if(show && !firstVisibleValue){
        firstVisibleValue = opt.value;
      }
    });

    if(!modelSelect.value || modelSelect.selectedOptions[0]?.hidden){
      modelSelect.value = firstVisibleValue;
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
      const active = tab.getAttribute('data-type-tab') === nextType;
      tab.classList.toggle('is-active', active);
      tab.setAttribute('aria-selected', String(active));
    });

    promptInput.value = promptState[nextType].prompt || '';
    negativeInput.value = promptState[nextType].negative || '';
    refreshModelVisibility(nextType);

    document.querySelectorAll('.row-image-only').forEach((row)=>row.classList.toggle('is-hidden', nextType !== 'image'));
    document.querySelectorAll('.row-video-only').forEach((row)=>row.classList.toggle('is-hidden', nextType !== 'video'));
  };

  tabs.forEach((tab)=>tab.addEventListener('click', ()=>setType(tab.getAttribute('data-type-tab') || 'image')));
  setType(typeInput.value || 'image');
}

document.addEventListener('DOMContentLoaded',()=>{
  bindMobileNav();
  document.body.addEventListener('click', onDeleteGenerationClick);

  const f=document.getElementById('generateForm');
  if(f){
    setupGeneratorTabs(f);
    f.addEventListener('submit',submitGeneration);
    loadHistory();
    setInterval(loadHistory, 8000);
  }
});
