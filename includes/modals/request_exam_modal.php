<!-- Request Exam Modal -->
<div class="modal fade" id="requestExamModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="request_exam.php" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Request Exam Access</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="exam_id" class="form-label">Select Exam</label>
                        <select class="form-select" id="exam_id" name="exam_id" required>
                            <option value="">Choose exam...</option>
                            <?php foreach ($available_exams as $exam): ?>
                                <?php if (!$exam['has_pending_request'] && !$exam['has_access']): ?>
                                    <option value="<?php echo $exam['id']; ?>">
                                        <?php echo htmlspecialchars($exam['title']); ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="preferred_date" class="form-label">Preferred Exam Date</label>
                        <input type="date" class="form-control" id="preferred_date" name="preferred_date" 
                               min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" 
                               max="<?php echo date('Y-m-d', strtotime('+30 days')); ?>" 
                               required>
                        <div class="form-text">Select a date between tomorrow and 30 days from now.</div>
                    </div>
                    <div class="mb-3">
                        <label for="request_reason" class="form-label">Request Reason</label>
                        <textarea class="form-control" id="request_reason" name="request_reason" 
                                  rows="3" required placeholder="Please explain why you want to take this exam..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Request</button>
                </div>
            </form>
        </div>
    </div>
</div>