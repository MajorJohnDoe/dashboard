<div class="nice-form-group" id="dialog-task-duplicate" style="width: 30rem; padding: 10px">
    <div class="audio-recorder-container">
        <canvas id="audioVisualizer" width="350" height="100" style="width: 100%; background: #ccc;"></canvas>
        <div class="audio-controls">
            <button type="button" id="recordButton" class="btn btn-red">Record</button>
            <button type="button" id="stopButton" class="btn btn-green" disabled>Finish</button>
            <input type="checkbox" id="visualizationToggle" checked>
            <label for="visualizationToggle">Enable Visualization</label>
        </div>
        <br>
        <div id="recordingStatus"></div>
        <div id="recordingSizeStatus"></div>
    </div>
</div>

<!--  -->

<script>
// Audio recorder initialization function
function initAudioRecorder() {
    let mediaRecorder;
    let audioChunks = [];
    let audioContext;
    let analyser;
    let isRecording = false;
    let isPaused = false;
    let isVisualizationEnabled = true;
    let visualizationFrameId = null;
    let transcriptionCount = 0;
    let maxFileSize = 0;
    let currentFileSize = 0;
    let recordingInterval;

    const recordButton = document.getElementById('recordButton');
    const stopButton = document.getElementById('stopButton');
    const recordingStatus = document.getElementById('recordingStatus');
    const recordingSizeStatus = document.getElementById('recordingSizeStatus');
    const canvas = document.getElementById('audioVisualizer');
    const canvasCtx = canvas.getContext('2d');
    const visualizationToggle = document.getElementById('visualizationToggle');

    // Create and add the visualization toggle button
    /* const visualizationToggle = document.createElement('input'); */
    visualizationToggle.id = 'visualizationToggle';
    visualizationToggle.type = 'checkbox';
    visualizationToggle.checked = true;
    document.querySelector('.audio-controls').appendChild(visualizationToggle);

    const visualizeLabel = document.createElement('label');
    visualizeLabel.htmlFor = 'visualizationToggle';
    document.querySelector('.audio-controls').appendChild(visualizeLabel);

    recordButton.onclick = toggleRecording;
    stopButton.onclick = stopRecording;
    visualizationToggle.onclick = toggleVisualization;


    // Fetch the maximum file size from the server
    fetch('/stickynotes/audio-transcribe/save?get_max_size=1')
        .then(response => response.json())
        .then(data => {
            maxFileSize = data.max_size;
            recordingSizeStatus.textContent = `0 / ${(maxFileSize / (1024 * 1024)).toFixed(2)} MB`;
        })
        .catch(error => console.error('Error fetching max file size:', error));


    function toggleRecording() {
        if (!isRecording) {
            startRecording();
        } else {
            if (isPaused) {
                resumeRecording();
            } else {
                pauseRecording();
            }
        }
    }

    function startRecording() {
        navigator.mediaDevices.getUserMedia({ audio: true })
            .then(stream => {
                audioContext = new (window.AudioContext || window.webkitAudioContext)();
                analyser = audioContext.createAnalyser();
                const source = audioContext.createMediaStreamSource(stream);
                source.connect(analyser);
                
                mediaRecorder = new MediaRecorder(stream);
                mediaRecorder.ondataavailable = event => {
                    audioChunks.push(event.data);
                    currentFileSize += event.data.size;
                    updateSizeDisplay();
                    if (currentFileSize >= maxFileSize) {
                        stopRecording();
                    }
                };
                mediaRecorder.onstop = saveAudioFile;
                
                mediaRecorder.start(1000); // Collect data every second
                isRecording = true;
                isPaused = false;
                recordButton.textContent = 'Pause';
                stopButton.disabled = false;
                recordingStatus.textContent = 'Recording...';
                if (isVisualizationEnabled) {
                    visualize();
                }
                recordingInterval = setInterval(updateSizeDisplay, 1000);
            });
    }

    function updateSizeDisplay() {
        const sizeMB = (currentFileSize / (1024 * 1024)).toFixed(2);
        const maxSizeMB = (maxFileSize / (1024 * 1024)).toFixed(2);
        recordingSizeStatus.textContent = `${sizeMB} / ${maxSizeMB} MB`;
    }

    function pauseRecording() {
        mediaRecorder.pause();
        recordButton.textContent = 'Resume';
        recordingStatus.textContent = 'Paused';
        isPaused = true;
        clearInterval(recordingInterval);
    }

    function resumeRecording() {
        mediaRecorder.resume();
        recordButton.textContent = 'Pause';
        recordingStatus.textContent = 'Recording...';
        isPaused = false;
        recordingInterval = setInterval(updateSizeDisplay, 1000);
    }

    function stopRecording() {
        mediaRecorder.stop();
        isRecording = false;
        recordButton.textContent = 'Record';
        stopButton.disabled = true;
        recordingStatus.textContent = 'Saving...';
        cancelAnimationFrame(visualizationFrameId);
        clearInterval(recordingInterval);
    }

    function saveAudioFile() {
        const audioBlob = new Blob(audioChunks, { type: 'audio/wav' });
        const formData = new FormData();
        formData.append('audio', audioBlob, 'recording.wav');

        fetch('/stickynotes/audio-transcribe/save', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                recordingStatus.textContent = 'Audio saved successfully! Transcribing...';
                console.log('File saved as:', data.fileName);
                console.log('File path:', data.filePath);
                transcribeAudio(data.fileName);
            } else {
                throw new Error(data.message || 'Unknown error occurred');
            }
        })
        .catch(error => {
            console.error('Error saving audio:', error);
            recordingStatus.textContent = 'Error saving audio: ' + error.message;
        });

        audioChunks = [];
        currentFileSize = 0;
        updateSizeDisplay();
    }

    function transcribeAudio(fileName) {
        const formData = new FormData();
        formData.append('fileName', fileName);

        fetch('/stickynotes/audio-transcribe/process', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                transcriptionCount++;
                const transcriptionLabel = `\n\nTranscription #${transcriptionCount}:\n`;
                const transcribedText = transcriptionLabel + data.transcription + '\n';

                // Append transcribed text to TinyMCE editor
                const editor = tinymce.get('note_content');
                editor.setContent(editor.getContent() + transcribedText);
                
                // Move cursor to the end of the content
                editor.selection.select(editor.getBody(), true);
                editor.selection.collapse(false);
                
                recordingStatus.textContent = 'Transcription completed and appended!';
            } else {
                recordingStatus.textContent = 'Transcription failed: ' + data.message;
            }
        })
        .catch(error => {
            console.error('Error during transcription:', error);
            recordingStatus.textContent = 'Error during transcription. Please try again.';
        });
    }
    
    function visualize() {
        if (!isRecording || !isVisualizationEnabled) return;

        analyser.fftSize = 256;
        const bufferLength = analyser.frequencyBinCount;
        const dataArray = new Uint8Array(bufferLength);

        canvasCtx.clearRect(0, 0, canvas.width, canvas.height);

        function draw() {
            visualizationFrameId = requestAnimationFrame(draw);
            analyser.getByteFrequencyData(dataArray);

            canvasCtx.fillStyle = 'rgb(200, 200, 200)';
            canvasCtx.fillRect(0, 0, canvas.width, canvas.height);

            const barWidth = (canvas.width / bufferLength) * 2.5;
            let barHeight;
            let x = 0;

            for (let i = 0; i < bufferLength; i++) {
                barHeight = dataArray[i] / 2;

                canvasCtx.fillStyle = `rgb(${barHeight + 100}, 50, 50)`;
                canvasCtx.fillRect(x, canvas.height - barHeight / 2, barWidth, barHeight);

                x += barWidth + 1;
            }
        }

        draw();
    }


    function toggleVisualization() {
        isVisualizationEnabled = !isVisualizationEnabled;
        visualizationToggle.textContent = isVisualizationEnabled ? 'Disable Visualization' : 'Enable Visualization';
        if (isVisualizationEnabled && isRecording) {
            visualize();
        } else if (!isVisualizationEnabled) {
            canvasCtx.clearRect(0, 0, canvas.width, canvas.height);
        }
    }
}

// Call this function to initialize the audio recorder
initAudioRecorder();



</script>