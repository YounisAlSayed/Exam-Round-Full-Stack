// ============================================
        // TIMER
        // ============================================
        let timeRemaining = <?= $timeRemaining ?>;
        let timerInterval = null;

        function formatTime(seconds) {
            const hours = Math.floor(seconds / 3600);
            const minutes = Math.floor((seconds % 3600) / 60);
            const secs = seconds % 60;
            return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
        }

        function updateTimer() {
            if (timeRemaining <= 0) {
                clearInterval(timerInterval);
                document.getElementById('timerText').textContent = '00:00:00';
                document.getElementById('timerDisplay').classList.add('warning');
                alert('Time is up! Submitting your exam...');
                document.getElementById('examForm').submit();
                return;
            }

            timeRemaining--;
            document.getElementById('timerText').textContent = formatTime(timeRemaining);

            // Update progress bar
            const progress = (timeRemaining / <?= $timeLimit ?>) * 100;
            const progressBar = document.getElementById('timerProgress');
            progressBar.style.width = progress + '%';

            // Color warnings
            if (progress < 20) {
                progressBar.className = 'progress-timer-bar danger';
                document.getElementById('timerDisplay').classList.add('warning');
            } else if (progress < 50) {
                progressBar.className = 'progress-timer-bar warning';
            }
        }

        // Start timer if time remaining > 0
        if (timeRemaining > 0) {
            timerInterval = setInterval(updateTimer, 1000);
        }

        // ============================================
        // CONFIRM SUBMIT
        // ============================================
        document.getElementById('confirmSubmit')?.addEventListener('click', function(e) {
            <?php if ($unanswered > 0): ?>
                const confirmSubmit = confirm(
                    'You have <?= $unanswered ?> unanswered question(s).\n\n' +
                    'Are you sure you want to submit?'
                );
                if (!confirmSubmit) {
                    e.preventDefault();
                    return;
                }
            <?php endif; ?>
            // Submit the form
            document.getElementById('examForm').submit();
        });