const startBtn = document.getElementById('startBtn');
const stopBtn = document.getElementById('stopBtn');
const withEnglish = document.getElementById('withEnglish');
const statusBox = document.getElementById('status');
const transcriptBox = document.getElementById('transcript');
const transcriptYaleBox = document.getElementById('transcriptYale');
const replyCantoneseBox = document.getElementById('replyCantonese');
const replyCantoneseYaleBox = document.getElementById('replyCantoneseYale');
const replyEnglishBox = document.getElementById('replyEnglish');
const audioPlayer = document.getElementById('audioPlayer');
const historyBox = document.getElementById('history');
const clearHistoryBtn = document.getElementById('clearHistoryBtn');

let mediaRecorder = null;
let chunks = [];
let currentReplyCantonese = '';
let currentReplyYale = '';
let currentEnglish = '';
let currentAlignmentPairs = [];
let activeEnglishKey = null;

function setStatus(msg, level = 'secondary') {
  statusBox.className = `alert alert-${level} py-2 mb-3`;
  statusBox.textContent = msg;
}

function blobToDataURI(blob) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onloadend = () => resolve(reader.result);
    reader.onerror = reject;
    reader.readAsDataURL(blob);
  });
}

function escapeHtml(str) {
  return String(str)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#39;');
}

function normalizeEnglishWord(word) {
  return word.toLowerCase().replace(/[^a-z0-9']/g, '');
}

function escapeRegExp(str) {
  return String(str).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function highlightPhrase(text, phrase) {
  if (!phrase || !text) return escapeHtml(text || '');
  const pattern = new RegExp(escapeRegExp(phrase), 'gi');
  return escapeHtml(text).replace(pattern, (m) => `<span class="hl-match">${m}</span>`);
}

function highlightMappedPhrase(text, mappedPhrases) {
  if (!text) return '-';
  if (!Array.isArray(mappedPhrases) || mappedPhrases.length === 0) return escapeHtml(text);

  let html = escapeHtml(text);
  mappedPhrases
    .filter(Boolean)
    .sort((a, b) => b.length - a.length)
    .forEach((phrase) => {
      const pattern = new RegExp(escapeRegExp(phrase), 'gi');
      html = html.replace(pattern, (m) => `<span class="hl-match">${m}</span>`);
    });
  return html;
}

function buildAlignmentIndex(pairs) {
  const index = new Map();
  if (!Array.isArray(pairs)) return index;

  pairs.forEach((pair) => {
    if (!pair || typeof pair !== 'object') return;
    const key = normalizeEnglishWord(pair.english || '');
    if (!key) return;
    if (!index.has(key)) index.set(key, []);
    index.get(key).push({
      cantonese: String(pair.cantonese || '').trim(),
      yale: String(pair.yale || '').trim(),
    });
  });

  return index;
}

function renderEnglishExplanation(text, pairs) {
  if (!text) {
    replyEnglishBox.textContent = '-';
    return;
  }

  const alignmentIndex = buildAlignmentIndex(pairs);
  const tokens = text.split(/(\b[\w']+\b)/);
  const html = tokens.map((token) => {
    if (!/\b[\w']+\b/.test(token)) {
      return escapeHtml(token);
    }
    const key = normalizeEnglishWord(token);
    const clickable = alignmentIndex.has(key);
    const cls = clickable
      ? `eng-token${activeEnglishKey === key ? ' active' : ''}`
      : '';
    const attr = clickable ? ` data-eng-key="${escapeHtml(key)}"` : '';
    return `<span class="${cls}"${attr}>${escapeHtml(token)}</span>`;
  }).join('');

  replyEnglishBox.innerHTML = html;

  if (!activeEnglishKey || !alignmentIndex.has(activeEnglishKey)) {
    replyCantoneseBox.textContent = currentReplyCantonese || '-';
    replyCantoneseYaleBox.textContent = currentReplyYale || '-';
    return;
  }

  const entries = alignmentIndex.get(activeEnglishKey) || [];
  const cantonesePhrases = entries.map((e) => e.cantonese).filter(Boolean);
  const yalePhrases = entries.map((e) => e.yale).filter(Boolean);

  replyCantoneseBox.innerHTML = highlightMappedPhrase(currentReplyCantonese, cantonesePhrases);
  replyCantoneseYaleBox.innerHTML = highlightMappedPhrase(currentReplyYale, yalePhrases);
}

replyEnglishBox.addEventListener('click', (event) => {
  const target = event.target;
  if (!(target instanceof HTMLElement)) return;
  const key = target.dataset.engKey;
  if (!key) return;

  activeEnglishKey = (activeEnglishKey === key) ? null : key;
  renderEnglishExplanation(currentEnglish, currentAlignmentPairs);
});

async function loadHistory() {
  const res = await fetch('api/history.php');
  const data = await res.json();
  if (!data.ok || !Array.isArray(data.items) || data.items.length === 0) {
    historyBox.textContent = 'No history yet.';
    return;
  }

  historyBox.innerHTML = data.items.map((item) => {
    const english = item.explanation_english ? `<div><strong>EN:</strong> ${escapeHtml(item.explanation_english)}</div>` : '';
    const youYale = item.transcript_yale ? `<div class="text-muted"><strong>You (Yale):</strong> ${escapeHtml(item.transcript_yale)}</div>` : '';
    const coachYale = item.reply_cantonese_yale ? `<div class="text-muted"><strong>Coach (Yale):</strong> ${escapeHtml(item.reply_cantonese_yale)}</div>` : '';
    return `
      <div class="entry">
        <div class="text-muted">${escapeHtml(item.created_at)} UTC</div>
        <div><strong>You:</strong> ${escapeHtml(item.transcript)}</div>
        ${youYale}
        <div><strong>Coach:</strong> ${escapeHtml(item.reply_cantonese)}</div>
        ${coachYale}
        ${english}
      </div>
    `;
  }).join('');
}

startBtn.addEventListener('click', async () => {
  try {
    const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
    chunks = [];
    mediaRecorder = new MediaRecorder(stream, { mimeType: 'audio/webm' });
    mediaRecorder.ondataavailable = (e) => chunks.push(e.data);
    mediaRecorder.onstop = async () => {
      setStatus('Processing speech...', 'info');
      const blob = new Blob(chunks, { type: mediaRecorder.mimeType || 'audio/webm' });
      const dataUri = await blobToDataURI(blob);

      const response = await fetch('api/practice.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          audio_data: dataUri,
          with_english: withEnglish.checked,
        }),
      });

      const payload = await response.json();
      if (!payload.ok) {
        setStatus(payload.error || 'Request failed', 'danger');
        return;
      }

      transcriptBox.textContent = payload.transcript || '-';
      transcriptYaleBox.textContent = payload.transcript_yale || '-';

      currentReplyCantonese = payload.reply_cantonese || '';
      currentReplyYale = payload.reply_cantonese_yale || '';
      currentEnglish = payload.explanation_english || '';
      currentAlignmentPairs = Array.isArray(payload.alignment_pairs) ? payload.alignment_pairs : [];
      activeEnglishKey = null;

      replyCantoneseBox.textContent = currentReplyCantonese || '-';
      replyCantoneseYaleBox.textContent = currentReplyYale || '-';
      renderEnglishExplanation(currentEnglish, currentAlignmentPairs);

      if (payload.audio_url) {
        audioPlayer.src = payload.audio_url;
        audioPlayer.play().catch(() => {});
      }

      setStatus('Done. Tap English words to highlight mapped Cantonese.', 'success');
      await loadHistory();
    };

    mediaRecorder.start();
    startBtn.disabled = true;
    stopBtn.disabled = false;
    setStatus('Recording... tap Stop when done.', 'warning');
  } catch (err) {
    setStatus(`Microphone error: ${err.message}`, 'danger');
  }
});

stopBtn.addEventListener('click', () => {
  if (!mediaRecorder) return;
  mediaRecorder.stop();
  mediaRecorder.stream.getTracks().forEach((track) => track.stop());
  startBtn.disabled = false;
  stopBtn.disabled = true;
});

clearHistoryBtn.addEventListener('click', async () => {
  const res = await fetch('api/clear_history.php', { method: 'POST' });
  const data = await res.json();
  if (data.ok) {
    await loadHistory();
    setStatus('History cleared.', 'secondary');
  }
});

loadHistory().catch(() => {
  setStatus('Failed to load history.', 'danger');
});
