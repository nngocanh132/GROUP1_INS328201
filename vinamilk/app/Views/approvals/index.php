<?php
/**
 * Approvals & Reviews View (MVC)
 * Variables provided by ApprovalController::index():
 *   $user, $pending_counterparties, $pending_proposals, $pending_reviews
 *   $pending_approvals, $act_approval, $history, $total_pending
 */
$page_title = 'Approvals & Reviews';
$active_nav = 'approvals';
include __DIR__ . '/../../../includes/layout_head.php';
?>

<div class="page-header">
  <div class="page-header-title">
    <h1>Approvals &amp; Reviews</h1>
    <p>Review and approve contracts, partner companies, and contract proposals.</p>
  </div>
</div>

<?php if ($total_pending > 0): ?>
<div class="alert alert-warning" style="margin-bottom:16px;">
  <?= icon_svg('alert-circle') ?>
  <span><strong><?= $total_pending ?> item<?= $total_pending > 1 ? 's' : '' ?></strong> awaiting your action.</span>
</div>
<?php elseif (empty($pending_reviews)): ?>
<div class="alert alert-info" style="margin-bottom:16px;">
  <?= icon_svg('info') ?>
  <span>No items pending your action at this time.</span>
</div>
<?php endif; ?>

<?php /* ============================================================
         SECTION 1: PENDING COUNTERPARTIES (admin)
         ============================================================ */ ?>
<?php if (!empty($pending_counterparties)): ?>
<div class="card" style="margin-bottom:20px;">
  <div class="card-header">
    <span class="card-title"><?= icon_svg('users') ?> Pending Partner Companies</span>
    <span class="badge badge-pending"><?= count($pending_counterparties) ?></span>
  </div>
  <div class="table-wrapper">
    <table class="data-table">
      <thead>
        <tr><th>Code</th><th>Company Name</th><th>Type</th><th>Tax Code</th><th>Proposed By</th><th>Date</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($pending_counterparties as $cp): ?>
        <tr>
          <td class="td-ref"><?= e($cp['CounterpartyCode']) ?></td>
          <td>
            <span style="font-weight:600;"><?= e($cp['CompanyName']) ?></span>
            <?php if ($cp['LegalRepresentative']): ?>
            <div class="td-meta">Rep: <?= e($cp['LegalRepresentative']) ?></div>
            <?php endif; ?>
          </td>
          <td><?= e($cp['CounterpartyType']) ?></td>
          <td class="td-meta text-mono"><?= e($cp['TaxCode']) ?></td>
          <td class="td-meta"><?= e($cp['ProposerName'] ?? '—') ?></td>
          <td class="td-meta"><?= fmt_date($cp['ProposedAt']) ?></td>
          <td>
            <form method="POST" style="display:inline;">
              <?= csrf_field() ?>
              <input type="hidden" name="post_action"     value="approve_counterparty">
              <input type="hidden" name="counterparty_id" value="<?= $cp['CounterpartyID'] ?>">
              <button type="submit" class="btn btn-ghost btn-sm"
                      style="color:var(--color-success);border-color:var(--color-success);"
                      data-confirm="Approve &quot;<?= e($cp['CompanyName']) ?>&quot; and activate their account?">
                <?= icon_svg('check-circle') ?> Approve
              </button>
            </form>
            <button type="button" class="btn btn-ghost btn-sm"
                    style="color:var(--color-danger);border-color:var(--color-danger);"
                    onclick="openCpRejectModal(<?= $cp['CounterpartyID'] ?>, '<?= e(addslashes($cp['CompanyName'])) ?>')">
              <?= icon_svg('x') ?> Reject
            </button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php /* ============================================================
         SECTION 2: PENDING CONTRACT PROPOSALS (admin)
         ============================================================ */ ?>
<?php if (!empty($pending_proposals)): ?>
<div class="card" style="margin-bottom:20px;">
  <div class="card-header">
    <span class="card-title"><?= icon_svg('file-plus') ?> Contract Proposals Awaiting Admin Review</span>
    <span class="badge badge-review"><?= count($pending_proposals) ?></span>
  </div>
  <div class="table-wrapper">
    <table class="data-table">
      <thead>
        <tr><th>Code</th><th>Title</th><th>Partner</th><th>Type</th><th>Est. Value</th><th>Proposed By</th><th>Partner Note</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($pending_proposals as $p): ?>
        <tr>
          <td class="td-ref">
            <a href="<?= BASE_URL ?>/pages/requests.php?tab=proposals&pstatus=PartnerApproved"
               style="color:var(--color-brand-blue);font-weight:600;">
              <?= e($p['ProposalCode']) ?>
            </a>
          </td>
          <td>
            <a href="<?= BASE_URL ?>/pages/requests.php?tab=proposals&pstatus=PartnerApproved"
               style="font-weight:600;color:var(--color-text-primary);text-decoration:none;">
              <?= e(mb_strimwidth($p['ProposalTitle'], 0, 45, '…')) ?>
            </a>
            <div class="td-meta"><?= e($p['DepartmentName']) ?></div>
          </td>
          <td><?= e($p['CounterpartyName']) ?></td>
          <td class="td-meta"><?= e($p['TypeName']) ?></td>
          <td class="td-meta"><?= $p['EstimatedValue'] ? money($p['EstimatedValue'], $p['Currency']) : '—' ?></td>
          <td class="td-meta"><?= e($p['ProposerName']) ?></td>
          <td class="td-meta"><?= $p['PartnerReviewNote'] ? e(mb_strimwidth($p['PartnerReviewNote'], 0, 40, '…')) : '—' ?></td>
          <td>
            <button type="button" class="btn btn-ghost btn-sm"
                    style="color:var(--color-success);border-color:var(--color-success);"
                    onclick="openProposalModal(<?= $p['ProposalID'] ?>, '<?= e(addslashes($p['ProposalTitle'])) ?>', 'approve')">
              <?= icon_svg('check-circle') ?> Approve
            </button>
            <button type="button" class="btn btn-ghost btn-sm"
                    style="color:var(--color-danger);border-color:var(--color-danger);"
                    onclick="openProposalModal(<?= $p['ProposalID'] ?>, '<?= e(addslashes($p['ProposalTitle'])) ?>', 'reject')">
              <?= icon_svg('x') ?> Reject
            </button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php /* ============================================================
         SECTION 3: PENDING LEGAL REVIEWS
         ============================================================ */ ?>
<?php if (!empty($pending_reviews)): ?>
<div class="card" style="margin-bottom:20px;">
  <div class="card-header">
    <span class="card-title"><?= icon_svg('eye') ?> Pending Legal Reviews</span>
    <span class="badge badge-review"><?= count($pending_reviews) ?></span>
  </div>
  <div class="table-wrapper">
    <table class="data-table">
      <thead>
        <tr><th>Contract</th><th>Counterparty</th><th>Reviewer</th><th>Submitted</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($pending_reviews as $rv): ?>
        <tr>
          <td>
            <a href="<?= BASE_URL ?>/pages/contract_detail.php?id=<?= $rv['ContractID'] ?>"
               style="font-weight:600;"><?= e($rv['ContractCode']) ?></a>
            <div class="td-meta">
              <a href="<?= BASE_URL ?>/pages/contract_detail.php?id=<?= $rv['ContractID'] ?>"
                 style="color:var(--color-text-secondary);text-decoration:none;">
                <?= e(mb_strimwidth($rv['ContractTitle'], 0, 45, '…')) ?>
              </a>
            </div>
          </td>
          <td><?= e($rv['CounterpartyName']) ?></td>
          <td><?= e($rv['ReviewerName']) ?></td>
          <td class="td-meta"><?= fmt_datetime($rv['SubmittedAt']) ?></td>
          <td>
            <?php $can_review = ($user['role'] === 'admin' || (int)$rv['ReviewerAccountID'] === (int)$user['account_id']); ?>
            <?php if ($can_review): ?>
            <button type="button" class="btn btn-primary btn-sm"
                    onclick="openReviewModal(<?= $rv['ReviewID'] ?>, '<?= e(addslashes($rv['ContractCode'])) ?>', '<?= e(addslashes($rv['ContractTitle'])) ?>')">
              <?= icon_svg('edit') ?> Complete Review
            </button>
            <?php endif; ?>
            <a href="<?= BASE_URL ?>/pages/contract_detail.php?id=<?= $rv['ContractID'] ?>"
               class="btn btn-ghost btn-sm"><?= icon_svg('eye') ?> View</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php /* ============================================================
         SECTION 4: PENDING APPROVALS (admin only)
         ============================================================ */ ?>
<?php if ($user['role'] === 'admin'): ?>

<?php if ($act_approval): ?>
<div class="card" style="margin-bottom:20px;">
  <div class="card-header">
    <span class="card-title"><?= icon_svg('check-circle') ?> Approval Decision — <?= e($act_approval['ContractCode']) ?></span>
    <a href="<?= BASE_URL ?>/pages/contract_detail.php?id=<?= $act_approval['ContractID'] ?>"
       class="btn btn-ghost btn-sm" target="_blank">
      <?= icon_svg('eye') ?> View Full Contract
    </a>
  </div>
  <div class="card-body" style="padding:24px;">
    <div class="detail-grid" style="margin-bottom:20px;">
      <div class="detail-item">
        <div class="detail-label">Contract</div>
        <div class="detail-value">
          <a href="<?= BASE_URL ?>/pages/contract_detail.php?id=<?= $act_approval['ContractID'] ?>"
             style="font-weight:600;color:var(--color-brand-blue);">
            <?= e($act_approval['ContractCode']) ?>
          </a>
          — <?= e($act_approval['ContractTitle']) ?>
        </div>
      </div>
      <div class="detail-item">
        <div class="detail-label">Counterparty</div>
        <div class="detail-value"><?= e($act_approval['CounterpartyName']) ?></div>
      </div>
      <div class="detail-item">
        <div class="detail-label">Contract Owner</div>
        <div class="detail-value"><?= e($act_approval['OwnerName']) ?></div>
      </div>
      <div class="detail-item">
        <div class="detail-label">Contract Value</div>
        <div class="detail-value"><?= money($act_approval['ContractValue'], $act_approval['Currency'] ?? 'VND') ?></div>
      </div>
      <div class="detail-item">
        <div class="detail-label">Start Date</div>
        <div class="detail-value"><?= fmt_date($act_approval['StartDate']) ?></div>
      </div>
      <div class="detail-item">
        <div class="detail-label">End Date</div>
        <div class="detail-value"><?= fmt_date($act_approval['EndDate']) ?></div>
      </div>
    </div>
    <form method="POST" action="<?= BASE_URL ?>/pages/approvals.php" id="approvalForm">
      <?= csrf_field() ?>
      <input type="hidden" name="post_action" value="decide_approval">
      <input type="hidden" name="approval_id" value="<?= $act_approval['ApprovalID'] ?>">
      <div class="form-group" style="margin-bottom:16px;">
        <label style="font-weight:600;display:block;margin-bottom:8px;">Decision <span class="req">*</span></label>
        <div style="display:flex;gap:20px;">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:500;">
            <input type="radio" name="approval_action" value="Approved" style="accent-color:var(--color-success);">
            <span style="color:var(--color-success);"><?= icon_svg('check-circle') ?> Approve</span>
          </label>
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:500;">
            <input type="radio" name="approval_action" value="Rejected" style="accent-color:var(--color-danger);">
            <span style="color:var(--color-danger);"><?= icon_svg('x') ?> Reject / Request Revision</span>
          </label>
        </div>
      </div>
      <div class="form-group" style="margin-bottom:20px;">
        <label for="comments">Comments <span style="color:var(--color-text-muted);font-weight:400;">(required if rejecting)</span></label>
        <textarea id="comments" name="comments" class="form-control" rows="3"
                  placeholder="Enter approval notes or revision requirements…"></textarea>
      </div>
      <div style="display:flex;justify-content:flex-end;gap:10px;">
        <a href="<?= BASE_URL ?>/pages/approvals.php" class="btn btn-ghost">Cancel</a>
        <button type="submit" class="btn btn-primary">Submit Decision</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php if (!empty($pending_approvals)): ?>
<div class="card" style="margin-bottom:20px;">
  <div class="card-header">
    <span class="card-title"><?= icon_svg('check-circle') ?> Pending Approval Queue</span>
    <span class="badge badge-pending"><?= count($pending_approvals) ?></span>
  </div>
  <div class="table-wrapper">
    <table class="data-table">
      <thead>
        <tr><th>Contract</th><th>Counterparty</th><th>Value</th><th>Level</th><th>Submitted</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach ($pending_approvals as $ap): ?>
        <tr>
          <td>
            <a href="<?= BASE_URL ?>/pages/contract_detail.php?id=<?= $ap['ContractID'] ?>"
               style="font-weight:600;"><?= e($ap['ContractCode']) ?></a>
            <div class="td-meta">
              <a href="<?= BASE_URL ?>/pages/contract_detail.php?id=<?= $ap['ContractID'] ?>"
                 style="color:var(--color-text-secondary);text-decoration:none;">
                <?= e(mb_strimwidth($ap['ContractTitle'], 0, 45, '…')) ?>
              </a>
            </div>
          </td>
          <td><?= e($ap['CounterpartyName']) ?></td>
          <td class="td-meta"><?= money($ap['ContractValue'], $ap['Currency'] ?? 'VND') ?></td>
          <td class="text-center"><?= $ap['ApprovalLevel'] ?></td>
          <td class="td-meta"><?= fmt_datetime($ap['SubmittedAt']) ?></td>
          <td>
            <a href="<?= BASE_URL ?>/pages/contract_detail.php?id=<?= $ap['ContractID'] ?>"
               class="btn btn-ghost btn-sm"><?= icon_svg('eye') ?> View</a>
            <a href="<?= BASE_URL ?>/pages/approvals.php?act=<?= $ap['ApprovalID'] ?>"
               class="btn btn-primary btn-sm"><?= icon_svg('check-circle') ?> Decide</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php elseif (empty($act_approval)): ?>
<div class="card" style="margin-bottom:20px;">
  <div class="card-body" style="text-align:center;padding:32px;color:var(--color-text-muted);">
    <?= icon_svg('check-circle') ?> No contracts pending your approval.
  </div>
</div>
<?php endif; ?>

<!-- Approval history -->
<?php if (!empty($history)): ?>
<div class="card" style="margin-bottom:20px;">
  <div class="card-header">
    <span class="card-title">Recent Approval History</span>
    <span style="font-size:12px;color:var(--color-text-muted);">Last 20 decisions</span>
  </div>
  <div class="table-wrapper">
    <table class="data-table">
      <thead>
        <tr><th>Contract</th><th>Decision</th><th>Approver</th><th>Comments</th><th>Date</th></tr>
      </thead>
      <tbody>
        <?php foreach ($history as $h): ?>
        <tr>
          <td>
            <a href="<?= BASE_URL ?>/pages/contract_detail.php?id=<?= $h['ContractID'] ?>"
               style="font-weight:600;"><?= e($h['ContractCode']) ?></a>
            <div class="td-meta">
              <a href="<?= BASE_URL ?>/pages/contract_detail.php?id=<?= $h['ContractID'] ?>"
                 style="color:var(--color-text-secondary);text-decoration:none;">
                <?= e(mb_strimwidth($h['ContractTitle'], 0, 40, '…')) ?>
              </a>
            </div>
          </td>
          <td><?= status_badge($h['ApprovalStatus']) ?></td>
          <td><?= e($h['ApproverName']) ?></td>
          <td style="font-size:12px;"><?= e(mb_strimwidth($h['Comments'] ?? '—', 0, 60, '…')) ?></td>
          <td class="td-meta"><?= fmt_datetime($h['ApprovedAt']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php else: /* Non-admin: show their own review history */ ?>
<?php
$my_review_history = db_query(
    "SELECT r.*, c.ContractCode, c.ContractTitle, cp.CompanyName AS CounterpartyName
     FROM Review r
     JOIN Contract c ON c.ContractID=r.ContractID
     JOIN Counterparty cp ON cp.CounterpartyID=c.CounterpartyID
     WHERE r.ReviewerAccountID=? AND r.ReviewStatus!='Pending'
     ORDER BY r.ReviewedAt DESC LIMIT 10",
    [$user['account_id']]
)->fetchAll();
?>
<?php if (!empty($my_review_history)): ?>
<div class="card">
  <div class="card-header"><span class="card-title">My Review History</span></div>
  <div class="table-wrapper">
    <table class="data-table">
      <thead>
        <tr><th>Contract</th><th>Counterparty</th><th>Decision</th><th>Comments</th><th>Reviewed At</th></tr>
      </thead>
      <tbody>
        <?php foreach ($my_review_history as $rv): ?>
        <tr>
          <td>
            <a href="<?= BASE_URL ?>/pages/contract_detail.php?id=<?= $rv['ContractID'] ?>"
               style="font-weight:600;"><?= e($rv['ContractCode']) ?></a>
            <div class="td-meta">
              <a href="<?= BASE_URL ?>/pages/contract_detail.php?id=<?= $rv['ContractID'] ?>"
                 style="color:var(--color-text-secondary);text-decoration:none;">
                <?= e(mb_strimwidth($rv['ContractTitle'], 0, 45, '…')) ?>
              </a>
            </div>
          </td>
          <td><?= e($rv['CounterpartyName']) ?></td>
          <td><?= status_badge($rv['ReviewStatus']) ?></td>
          <td style="font-size:12px;"><?= e(mb_strimwidth($rv['Comments'] ?? '—', 0, 60, '…')) ?></td>
          <td class="td-meta"><?= fmt_datetime($rv['ReviewedAt']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
<?php endif; ?>

<?php /* ============================================================
         MODALS
         ============================================================ */ ?>

<!-- Review completion modal -->
<div id="reviewModal" style="display:none;position:fixed;inset:0;z-index:1000;
     background:rgba(0,0,0,.45);align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:12px;padding:28px;width:520px;max-width:95vw;
              box-shadow:0 20px 60px rgba(0,0,0,.2);">
    <h3 style="margin:0 0 4px;font-size:16px;">Complete Legal Review</h3>
    <p id="reviewModalSub" style="font-size:13px;color:var(--color-text-secondary);margin:0 0 20px;"></p>
    <form method="POST" id="reviewModalForm">
      <?= csrf_field() ?>
      <input type="hidden" name="post_action" value="complete_review">
      <input type="hidden" name="review_id" id="reviewModalId" value="">
      <div class="form-group" style="margin-bottom:16px;">
        <label style="font-weight:600;display:block;margin-bottom:8px;">Decision <span class="req">*</span></label>
        <div style="display:flex;flex-direction:column;gap:8px;">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
            <input type="radio" name="review_status" value="Approved" style="accent-color:var(--color-success);">
            <span style="color:var(--color-success);font-weight:500;"><?= icon_svg('check-circle') ?> Approved — forward to Admin for final approval</span>
          </label>
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
            <input type="radio" name="review_status" value="RevisionRequired" style="accent-color:#d97706;">
            <span style="color:#b45309;font-weight:500;"><?= icon_svg('alert-circle') ?> Revision Required — return to staff for corrections</span>
          </label>
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
            <input type="radio" name="review_status" value="Rejected" style="accent-color:var(--color-danger);">
            <span style="color:var(--color-danger);font-weight:500;"><?= icon_svg('x') ?> Rejected</span>
          </label>
        </div>
      </div>
      <div class="form-group" style="margin-bottom:20px;">
        <label for="review_comments">Comments <span style="color:var(--color-text-muted);font-weight:400;">(required for Revision / Rejection)</span></label>
        <textarea id="review_comments" name="comments" class="form-control" rows="3"
                  placeholder="Enter review notes or required changes…"></textarea>
      </div>
      <div style="display:flex;justify-content:flex-end;gap:10px;">
        <button type="button" class="btn btn-ghost" onclick="closeReviewModal()">Cancel</button>
        <button type="submit" class="btn btn-primary">Submit Review</button>
      </div>
    </form>
  </div>
</div>

<!-- Counterparty rejection modal -->
<div id="cpRejectModal" style="display:none;position:fixed;inset:0;z-index:1000;
     background:rgba(0,0,0,.45);align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:12px;padding:28px;width:480px;max-width:95vw;
              box-shadow:0 20px 60px rgba(0,0,0,.2);">
    <h3 style="margin:0 0 8px;font-size:16px;">Reject Partner Company</h3>
    <p id="cpRejectModalName" style="font-size:13px;color:var(--color-text-secondary);margin:0 0 20px;"></p>
    <form method="POST" id="cpRejectForm">
      <?= csrf_field() ?>
      <input type="hidden" name="post_action"     value="reject_counterparty">
      <input type="hidden" name="counterparty_id" id="cpRejectId" value="">
      <div class="form-group" style="margin-bottom:20px;">
        <label for="cp_rejection_reason">Rejection Reason <span class="req">*</span></label>
        <textarea id="cp_rejection_reason" name="rejection_reason" class="form-control" rows="3"
                  placeholder="Explain why this partner company is being rejected…" required></textarea>
      </div>
      <div style="display:flex;justify-content:flex-end;gap:10px;">
        <button type="button" class="btn btn-ghost" onclick="closeCpRejectModal()">Cancel</button>
        <button type="submit" class="btn btn-primary" style="background:var(--color-danger);border-color:var(--color-danger);">
          Confirm Rejection
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Proposal approve/reject modal -->
<div id="proposalModal" style="display:none;position:fixed;inset:0;z-index:1000;
     background:rgba(0,0,0,.45);align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:12px;padding:28px;width:520px;max-width:95vw;
              box-shadow:0 20px 60px rgba(0,0,0,.2);">
    <h3 id="proposalModalTitle" style="margin:0 0 8px;font-size:16px;"></h3>
    <p id="proposalModalSub" style="font-size:13px;color:var(--color-text-secondary);margin:0 0 20px;"></p>
    <form method="POST" id="proposalModalForm" action="<?= BASE_URL ?>/pages/requests.php?tab=proposals">
      <?= csrf_field() ?>
      <input type="hidden" name="post_action" id="proposalPostAction" value="">
      <input type="hidden" name="proposal_id" id="proposalModalId" value="">
      <div class="form-group" style="margin-bottom:20px;">
        <label for="proposal_admin_note">Review Note</label>
        <textarea id="proposal_admin_note" name="admin_note" class="form-control" rows="3"
                  placeholder="Optional note to the proposing staff member…"></textarea>
        <span id="proposalNoteHint" style="display:none;font-size:12px;color:var(--color-danger);margin-top:4px;">
          A note is required when rejecting.
        </span>
      </div>
      <div style="display:flex;justify-content:flex-end;gap:10px;">
        <button type="button" class="btn btn-ghost" onclick="closeProposalModal()">Cancel</button>
        <button type="submit" id="proposalSubmitBtn" class="btn btn-primary">Submit</button>
      </div>
    </form>
  </div>
</div>

<script>
/* Review modal */
function openReviewModal(reviewId, code, title) {
  document.getElementById('reviewModalId').value = reviewId;
  document.getElementById('reviewModalSub').textContent = code + ' — ' + title;
  document.getElementById('review_comments').value = '';
  document.querySelectorAll('input[name="review_status"]').forEach(r => r.checked = false);
  document.getElementById('reviewModal').style.display = 'flex';
}
function closeReviewModal() { document.getElementById('reviewModal').style.display = 'none'; }
document.getElementById('reviewModal').addEventListener('click', e => {
  if (e.target === document.getElementById('reviewModal')) closeReviewModal();
});
document.getElementById('reviewModalForm').addEventListener('submit', function(e) {
  const sel = this.querySelector('input[name="review_status"]:checked');
  if (!sel) { e.preventDefault(); alert('Please select a review decision.'); return; }
  const comments = document.getElementById('review_comments').value.trim();
  if (sel.value !== 'Approved' && !comments) {
    e.preventDefault();
    document.getElementById('review_comments').classList.add('is-invalid');
    document.getElementById('review_comments').focus();
  }
});
/* Counterparty reject modal */
function openCpRejectModal(cpId, cpName) {
  document.getElementById('cpRejectId').value = cpId;
  document.getElementById('cpRejectModalName').textContent = 'Company: ' + cpName;
  document.getElementById('cp_rejection_reason').value = '';
  document.getElementById('cpRejectModal').style.display = 'flex';
}
function closeCpRejectModal() { document.getElementById('cpRejectModal').style.display = 'none'; }
document.getElementById('cpRejectModal').addEventListener('click', e => {
  if (e.target === document.getElementById('cpRejectModal')) closeCpRejectModal();
});
/* Proposal modal */
function openProposalModal(propId, propTitle, mode) {
  document.getElementById('proposalModalId').value    = propId;
  document.getElementById('proposalPostAction').value = mode === 'approve' ? 'admin_approve' : 'admin_reject';
  document.getElementById('proposalModalTitle').textContent = mode === 'approve' ? 'Approve Contract Proposal' : 'Reject Contract Proposal';
  document.getElementById('proposalModalSub').textContent   = 'Proposal: ' + propTitle;
  document.getElementById('proposalNoteHint').style.display = mode === 'reject' ? '' : 'none';
  document.getElementById('proposal_admin_note').required   = mode === 'reject';
  document.getElementById('proposalSubmitBtn').textContent  = mode === 'approve' ? 'Approve' : 'Reject';
  document.getElementById('proposalSubmitBtn').style.background  = mode === 'approve' ? '' : 'var(--color-danger)';
  document.getElementById('proposalSubmitBtn').style.borderColor = mode === 'approve' ? '' : 'var(--color-danger)';
  document.getElementById('proposal_admin_note').value = '';
  document.getElementById('proposalModal').style.display = 'flex';
}
function closeProposalModal() { document.getElementById('proposalModal').style.display = 'none'; }
document.getElementById('proposalModal').addEventListener('click', e => {
  if (e.target === document.getElementById('proposalModal')) closeProposalModal();
});
</script>

<?php include __DIR__ . '/../../../includes/layout_foot.php'; ?>
