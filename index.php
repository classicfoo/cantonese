<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cantonese Coach (Qwen)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/styles.css" rel="stylesheet">
</head>
<body>
<main class="container py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-8">
            <div class="card shadow-sm border-0 app-shell">
                <div class="card-body p-4">
                    <h1 class="h3 mb-1">Cantonese Speaking Coach</h1>
                    <p class="text-secondary mb-4">Speak Cantonese, get a Cantonese reply, and optionally see a short English explanation.</p>

                    <div class="d-grid gap-2 d-sm-flex mb-3">
                        <button id="startBtn" class="btn btn-primary btn-lg flex-fill">Start Recording</button>
                        <button id="stopBtn" class="btn btn-outline-primary btn-lg flex-fill" disabled>Stop</button>
                    </div>

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="withEnglish" checked>
                        <label class="form-check-label" for="withEnglish">Include short English explanation</label>
                    </div>

                    <div id="status" class="alert alert-secondary py-2 mb-3">Ready.</div>

                    <section class="result-box mb-3">
                        <h2 class="h6 text-uppercase">You said</h2>
                        <p id="transcript" class="mb-0 text-body-emphasis">-</p>
                    </section>

                    <section class="result-box mb-3">
                        <h2 class="h6 text-uppercase">Coach reply (Cantonese)</h2>
                        <p id="replyCantonese" class="mb-0 text-body-emphasis">-</p>
                    </section>

                    <section class="result-box mb-3">
                        <h2 class="h6 text-uppercase">English explanation</h2>
                        <p id="replyEnglish" class="mb-0 text-body-emphasis">-</p>
                    </section>

                    <audio id="audioPlayer" class="w-100 mb-2" controls></audio>
                </div>
            </div>

            <div class="card shadow-sm border-0 mt-3">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h5 mb-0">Recent Practice</h2>
                        <button id="clearHistoryBtn" class="btn btn-sm btn-outline-danger">Clear</button>
                    </div>
                    <div id="history" class="small text-secondary">No history yet.</div>
                </div>
            </div>
        </div>
    </div>
</main>
<script src="assets/js/app.js"></script>
</body>
</html>
