function escapeHtml(value){
  return String(value ?? '')
    .replaceAll('&','&amp;')
    .replaceAll('<','&lt;')
    .replaceAll('>','&gt;')
    .replaceAll('"','&quot;')
    .replaceAll("'",'&#39;');
}

function mediaPreviewHtml(item){
  if(!item.output_path){
    return '<div class="history-thumb history-thumb-empty"><span>No preview yet</span></div>';
  }

  if(item.type === 'video'){
    return `<a class="history-thumb" href="${escapeHtml(item.output_path)}" target="_blank" rel="noopener"><video src="${escapeHtml(item.output_path)}" muted playsinline preload="metadata"></video></a>`;
  }

  return `<a class="history-thumb" href="${escapeHtml(item.output_path)}" target="_blank" rel="noopener"><img src="${escapeHtml(item.output_path)}" alt="Generated output preview"></a>`;
}

function historyDetailsHtml(item){
  const title = `<strong>${escapeHtml(item.type)}</strong> • ${escapeHtml(item.model_key)}`;
  const prompt = item.prompt ? `<span>${escapeHtml(item.prompt)}</span>` : '';
  const meta = `<small>${escapeHtml(item.status)}</small>`;
  const linkStart = item.output_path ? `<a class="history-main-link" href="${escapeHtml(item.output_path)}" target="_blank" rel="noopener">` : '<div class="history-main-link">';
  const linkEnd = item.output_path ? '</a>' : '</div>';

  return `${linkStart}${title}${meta}${prompt}${linkEnd}`;
}

async function submitGeneration(e){
  e.preventDefault();
  const form = e.target;
  const payload = Object.fromEntries(new FormData(form).entries());
  payload.duration_seconds = parseFloat(payload.duration_seconds || '5');
  payload.fps = parseInt(payload.fps || '24', 10);
  const res = await fetch('/api/generate.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
  const data = await res.json();
  document.getElementById('statusBox').textContent = JSON.stringify(data,null,2);
  await loadHistory();
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
    div.innerHTML=`${mediaPreviewHtml(item)}<div class="history-content">${historyDetailsHtml(item)}<div class="history-actions">${item.output_path?`<a class="btn btn-secondary" href="/api/download.php?id=${encodeURIComponent(item.id)}">Download</a>`:''}<button class="btn btn-danger js-delete-generation" type="button" data-id="${escapeHtml(item.id)}">Delete</button></div></div>`;
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

document.addEventListener('DOMContentLoaded',()=>{
  bindMobileNav();
  document.body.addEventListener('click', onDeleteGenerationClick);

  const f=document.getElementById('generateForm');
  if(f){
    f.addEventListener('submit',submitGeneration);
    loadHistory();
    setInterval(loadHistory, 8000);
  }
});
