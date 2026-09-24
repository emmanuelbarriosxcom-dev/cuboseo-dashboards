const data = window.CUBO_DATA;

const STORAGE_KEY = "cubo-seo-editorial-dashboard-v1";
let state = JSON.parse(localStorage.getItem(STORAGE_KEY) || "{}");

function saveState() {
  localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
}

function ensureItemState(priority, defaultStatus) {
  if (!state[priority]) {
    state[priority] = {
      status: defaultStatus || "Pendiente",
      done: false,
      notes: ""
    };
  }
  if (state[priority].done) state[priority].status = "Hecho";
}

data.plan.forEach(item => ensureItemState(item["Priority"], item["Status"] || "Pendiente"));

const pillarFilter = document.getElementById('pillarFilter');
const statusFilter = document.getElementById('statusFilter');
const searchInput = document.getElementById('searchInput');
const articleList = document.getElementById('articleList');

function uniquePillars() {
  return [...new Set(data.plan.map(x => x["Pillar"]).filter(Boolean))];
}

uniquePillars().forEach(p => {
  const op = document.createElement('option');
  op.value = p;
  op.textContent = p;
  pillarFilter.appendChild(op);
});

function statusClass(status) {
  const s = (status || "").toLowerCase();
  if (s.includes("hecho")) return "status-hecho";
  if (s.includes("progreso")) return "status-en-progreso";
  return "status-pendiente";
}

function scoreAverage() {
  const vals = data.plan.map(x => Number(x["Score / 20"]) || 0).filter(Boolean);
  if (!vals.length) return "—";
  return (vals.reduce((a,b) => a+b, 0) / vals.length).toFixed(1) + "/20";
}

function dominantPillar() {
  const counts = {};
  data.plan.forEach(x => {
    const p = x["Pillar"] || "—";
    counts[p] = (counts[p] || 0) + 1;
  });
  return Object.entries(counts).sort((a,b) => b[1]-a[1])[0]?.[0] || "—";
}

document.getElementById('kpi-score').textContent = scoreAverage();
document.getElementById('kpi-pillar').textContent = dominantPillar();

function renderKPIs() {
  const done = data.plan.filter(x => state[x["Priority"]]?.status === "Hecho").length;
  const pct = Math.round((done / data.plan.length) * 100);
  document.getElementById('kpi-progress').textContent = pct + "%";
  document.getElementById('progressFill').style.width = pct + "%";
  document.getElementById('progressText').textContent = `${done} de 10 completados`;
}

function matchesFilter(item) {
  const pFilter = pillarFilter.value;
  const sFilter = statusFilter.value;
  const q = searchInput.value.trim().toLowerCase();
  const itemState = state[item["Priority"]] || {};
  const status = itemState.status || item["Status"] || "Pendiente";

  const haystack = [
    item["Article Title"],
    item["Focus Keyword"],
    item["Secondary Keywords"],
    item["Buyer Persona"],
    item["CTA"],
    item["LinkedIn Angle"],
    item["Pillar"]
  ].join(" ").toLowerCase();

  const pOk = pFilter === "Todos" || item["Pillar"] === pFilter;
  const sOk = sFilter === "Todos" || status === sFilter;
  const qOk = !q || haystack.includes(q);

  return pOk && sOk && qOk;
}

function articleTemplate(item) {
  const id = item["Priority"];
  const itemState = state[id] || {};
  const currentStatus = itemState.status || item["Status"] || "Pendiente";
  const isDone = itemState.done || currentStatus === "Hecho";
  const ext1 = item["External Source 1"] ? `<a href="${item["External Source 1"]}" target="_blank" rel="noopener">Fuente externa 1</a>` : "—";
  const ext2 = item["External Source 2"] ? `<a href="${item["External Source 2"]}" target="_blank" rel="noopener">Fuente externa 2</a>` : "—";
  const score = item["Score / 20"] || "";
  const html = `
    <article class="card article" data-priority="${id}" data-pillar="${item["Pillar"]}" data-status="${currentStatus}">
      <div class="article-head">
        <div class="priority">${id}</div>
        <div>
          <div class="article-title">${item["Article Title"]}</div>
          <div class="chips">
            <span class="chip"><strong>Pilar:</strong> ${item["Pillar"]}</span>
            <span class="chip"><strong>Keyword:</strong> ${item["Focus Keyword"]}</span>
            <span class="chip"><strong>Intent:</strong> ${item["Search Intent"]}</span>
            <span class="chip"><strong>Longitud:</strong> ${item["Recommended Length"]}</span>
            <span class="chip badge-status ${statusClass(currentStatus)}">${currentStatus}</span>
          </div>
        </div>
        <div class="head-actions">
          <div class="score">
            <small>score</small>
            <span>${score}</span>
          </div>
          <div class="toggle-icon">▾</div>
        </div>
      </div>
      <div class="article-body">
        <div class="mini-grid">
          <div class="metric"><div class="m-label">Traffic potential</div><div class="m-val">${item["Traffic Potential"] || "—"}</div></div>
          <div class="metric"><div class="m-label">Trend</div><div class="m-val">${item["Trend"] || "—"}</div></div>
          <div class="metric"><div class="m-label">Commercial fit</div><div class="m-val">${item["Commercial Fit"] || "—"}</div></div>
          <div class="metric"><div class="m-label">Authority</div><div class="m-val">${item["LinkedIn / Authority"] || "—"}</div></div>
          <div class="metric"><div class="m-label">Confidence</div><div class="m-val" style="font-size:18px;">${item["Confidence"] || "—"}</div></div>
        </div>

        <div class="grid-2">
          <div class="info">
            <h4>Buyer persona</h4>
            <p>${item["Buyer Persona"] || "—"}</p>
          </div>
          <div class="info">
            <h4>CTA recomendado</h4>
            <p>${item["CTA"] || "—"}</p>
          </div>
          <div class="info">
            <h4>Por qué publicarlo ahora</h4>
            <p>${item["Why Now"] || "—"}</p>
          </div>
          <div class="info">
            <h4>Evidencia / señal</h4>
            <p>${item["Evidence / Signal"] || "—"}</p>
          </div>
        </div>

        <div class="stack">
          <div class="info">
            <h4>Ángulo diferenciador</h4>
            <p>${item["Differentiating Angle"] || "—"}</p>
          </div>
          <div class="info">
            <h4>Outline / H2s</h4>
            <p>${item["Outline / H2s"] || "—"}</p>
          </div>
          <div class="grid-2">
            <div class="info">
              <h4>Keywords secundarias / cluster</h4>
              <p>${item["Secondary Keywords"] || "—"}</p>
            </div>
            <div class="info">
              <h4>Enlaces internos sugeridos</h4>
              <p>${item["Internal Links"] || "—"}</p>
            </div>
          </div>
          <div class="grid-2">
            <div class="info">
              <h4>Fuentes externas</h4>
              <ul>
                <li>${ext1}</li>
                <li>${ext2}</li>
              </ul>
            </div>
            <div class="info">
              <h4>Ángulo para LinkedIn</h4>
              <p>${item["LinkedIn Angle"] || "—"}</p>
            </div>
          </div>
        </div>

        <div class="editor-box">
          <div class="status-box">
            <h4>Seguimiento</h4>
            <div class="field">
              <label for="status-${id}">Estado</label>
              <select id="status-${id}" class="status-select" data-priority="${id}">
                <option value="Pendiente" ${currentStatus==="Pendiente" ? "selected" : ""}>Pendiente</option>
                <option value="En progreso" ${currentStatus==="En progreso" ? "selected" : ""}>En progreso</option>
                <option value="Hecho" ${currentStatus==="Hecho" ? "selected" : ""}>Hecho</option>
              </select>
            </div>
            <div class="row" style="margin-top:12px;">
              <label class="check">
                <input type="checkbox" class="done-check" data-priority="${id}" ${isDone ? "checked" : ""} />
                Marcar como completado
              </label>
            </div>
            <p class="small" style="margin-top:12px;">Consejo: usa “En progreso” cuando el brief ya está en redacción y “Hecho” cuando el artículo ya fue publicado o quedó finalizado.</p>
          </div>
          <div class="notes-box">
            <h4>Notas</h4>
            <textarea class="notes" data-priority="${id}" placeholder="Añade aquí observaciones, deadlines, estado de imágenes, pendiente de revisión, publicación en LinkedIn, etc.">${itemState.notes || ""}</textarea>
          </div>
        </div>
      </div>
    </article>
  `;
  return html;
}

function renderArticles() {
  const items = data.plan.filter(matchesFilter);
  articleList.innerHTML = items.map(articleTemplate).join("");
  bindArticleEvents();
}

function bindArticleEvents() {
  document.querySelectorAll('.article-head').forEach(head => {
    head.addEventListener('click', (e) => {
      if (e.target.closest('select') || e.target.closest('textarea') || e.target.closest('input')) return;
      head.parentElement.classList.toggle('open');
    });
  });

  document.querySelectorAll('.status-select').forEach(sel => {
    sel.addEventListener('change', () => {
      const id = sel.dataset.priority;
      ensureItemState(id, "Pendiente");
      state[id].status = sel.value;
      state[id].done = sel.value === "Hecho";
      saveState();
      renderKPIs();
      renderArticles();
    });
  });

  document.querySelectorAll('.done-check').forEach(chk => {
    chk.addEventListener('change', () => {
      const id = chk.dataset.priority;
      ensureItemState(id, "Pendiente");
      state[id].done = chk.checked;
      if (chk.checked) state[id].status = "Hecho";
      else if (state[id].status === "Hecho") state[id].status = "Pendiente";
      saveState();
      renderKPIs();
      renderArticles();
    });
  });

  document.querySelectorAll('.notes').forEach(area => {
    area.addEventListener('input', () => {
      const id = area.dataset.priority;
      ensureItemState(id, "Pendiente");
      state[id].notes = area.value;
      saveState();
    });
  });
}

function renderReserves() {
  const grid = document.getElementById('reserveGrid');
  grid.innerHTML = data.reserves.map(item => `
    <article class="card reserve-card">
      <div class="topline">
        <span class="chip"><strong>Reserva #${item["Reserve #"]}</strong></span>
        <span class="chip">${item["Pillar"] || "—"}</span>
      </div>
      <h3>${item["Article Idea"] || "—"}</h3>
      <p style="margin-bottom:12px;"><strong style="color:var(--text);">Focus keyword:</strong> ${item["Focus Keyword"] || "—"}</p>
      <p style="margin-bottom:12px;">${item["Why It Is Reserve"] || "—"}</p>
      <p><a href="${item["Primary Source"] || "#"}">Fuente principal</a></p>
    </article>
  `).join("");
}

function renderEvidence() {
  const grid = document.getElementById('evidenceGrid');
  grid.innerHTML = data.evidence.map(item => `
    <article class="card evidence-card">
      <div class="topline">
        <span class="chip"><strong>${item["Evidence"] || "—"}</strong></span>
      </div>
      <h3>${item["Observed Data"] || "—"}</h3>
      <p style="margin-bottom:12px;">${item["Strategic Reading"] || "—"}</p>
      <p class="small"><strong style="color:var(--text);">Fuente:</strong> ${item["Source"] || "—"}</p>
    </article>
  `).join("");
}

document.getElementById('expandAll').addEventListener('click', () => {
  document.querySelectorAll('.article').forEach(el => el.classList.add('open'));
});
document.getElementById('collapseAll').addEventListener('click', () => {
  document.querySelectorAll('.article').forEach(el => el.classList.remove('open'));
});

pillarFilter.addEventListener('change', renderArticles);
statusFilter.addEventListener('change', renderArticles);
searchInput.addEventListener('input', renderArticles);

renderReserves();
renderEvidence();
renderKPIs();
renderArticles();
saveState();
