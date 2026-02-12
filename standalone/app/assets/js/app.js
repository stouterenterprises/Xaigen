async function submitGeneration(e){
  e.preventDefault();
  const form = e.target;
  const payload = Object.fromEntries(new FormData(form).entries());
  payload.duration_seconds = parseFloat(payload.duration_seconds || '5');
  payload.fps = parseInt(payload.fps || '24', 10);
  const res = await fetch('/api/generate.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
  const data = await res.json();
  document.getElementById('statusBox').textContent = JSON.stringify(data,null,2);
  loadHistory();
}

async function loadHistory(){
  const res = await fetch('/api/history.php');
  const data = await res.json();
  const box = document.getElementById('historyBox');
  box.innerHTML = '';
  (data.items||[]).forEach(item=>{
    const div=document.createElement('div');
    div.className='card';
    div.innerHTML=`<strong>${item.type}</strong> • ${item.model_key}<br><small>${item.status}</small><br>${item.output_path?`<a href="/api/download.php?id=${item.id}">Download</a>`:''}`;
    box.appendChild(div);
  });
}

document.addEventListener('DOMContentLoaded',()=>{const f=document.getElementById('generateForm'); if(f){f.addEventListener('submit',submitGeneration); loadHistory();}});
