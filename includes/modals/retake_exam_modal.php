<!-- Retake Exam Modal -->
<div class="modal fade" id="retakeExamModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="request_retake.php" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Request Exam Retake</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="retake_exam_id" class="form-label">Select Exam</label>
                        <select class="form-select" id="retake_exam_id" name="exam_id" required>
                            <option value="">Choose exam to retake...</option>
                            <?php foreach ($completed_exams as $exam): ?>
                                <?php if (!$exam['has_pending_retake']): ?>
                                    <option value="<?php echo $exam['id']; ?>">
                                        <?php echo htmlspecialchars($exam['title']); ?> 
                                        (Best Score: <?php echo number_format($exam['best_score'], 1); ?>%)
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="retake_reason" class="form-label">Retake Reason</label>
                        <textarea class="form-control" id="retake_reason" name="retake_reason" 
                                  rows="3" required 
                                  placeholder="Please explain why you want to retake this exam..."></textarea>
                        <div class="form-text">
                            Provide a valid reason for requesting a retake. This will be reviewed by the administrator.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Retake Request</button>
                </div>
            </form>
        </div>
    </div>
</div>