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

function escapeHtml(str) {
  return String(str)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#39;');
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
      replyCantoneseBox.textContent = payload.reply_cantonese || '-';
      replyCantoneseYaleBox.textContent = payload.reply_cantonese_yale || '-';
      replyEnglishBox.textContent = payload.explanation_english || '-';

      if (payload.audio_url) {
        audioPlayer.src = payload.audio_url;
        audioPlayer.play().catch(() => {});
      }

      setStatus('Done. Speak again anytime.', 'success');
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
