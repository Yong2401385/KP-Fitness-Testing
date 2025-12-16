document.addEventListener('DOMContentLoaded', () => {
    let selectedMembershipId = null;
    const paymentModal = new bootstrap.Modal(document.getElementById('paymentModal'));
    const membershipPlansModal = new bootstrap.Modal(document.getElementById('membershipPlansModal'));
    const paymentDetailsModal = new bootstrap.Modal(document.getElementById('paymentDetailsModal'));
    const cancelMembershipModal = new bootstrap.Modal(document.getElementById('cancelMembershipModal'));

    // --- Upgrade/Purchase Logic ---
    document.querySelectorAll('.choose-plan-btn').forEach(button => {
        button.addEventListener('click', (e) => {
            selectedMembershipId = e.currentTarget.dataset.membershipId;
            membershipPlansModal.hide();
            paymentModal.show();
        });
    });

    document.querySelectorAll('.payment-method-btn').forEach(button => {
        button.addEventListener('click', (e) => {
            document.querySelectorAll('.payment-method-btn').forEach(btn => btn.classList.remove('active'));
            e.currentTarget.classList.add('active');
            document.getElementById('pay-now-btn').disabled = false;
        });
    });

    document.getElementById('pay-now-btn').addEventListener('click', () => {
        document.getElementById('payment-methods').classList.add('d-none');
        document.getElementById('payment-loading').classList.remove('d-none');

        setTimeout(() => {
            const body = new FormData();
            body.append('action', 'purchase');
            body.append('membershipId', selectedMembershipId);
            body.append('csrf_token', window.membershipConfig.csrfToken);

            fetch('../api/purchase_membership.php', {
                method: 'POST',
                body: body
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('payment-loading').classList.add('d-none');
                document.getElementById('payment-success').classList.remove('d-none');
                if (data.success) {
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    alert(data.message || 'Payment failed.');
                    window.location.reload();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred.');
                window.location.reload();
            });
        }, 3000);
    });

    // --- Payment Details Logic ---
    document.body.addEventListener('click', (e) => {
        if (e.target.matches('.view-payment-btn')) {
            const btn = e.target;
            document.getElementById('detail-id').textContent = '#' + btn.dataset.id;
            document.getElementById('detail-date').textContent = btn.dataset.date;
            document.getElementById('detail-amount').textContent = btn.dataset.amount;
            document.getElementById('detail-method').textContent = btn.dataset.method;
            document.getElementById('detail-status').textContent = btn.dataset.status;
            document.getElementById('detail-type').textContent = btn.dataset.type;
            document.getElementById('detail-desc').textContent = btn.dataset.desc;
            
            paymentDetailsModal.show();
        }
    });

    // --- Cancel Membership Logic ---
    const cancelBtn = document.getElementById('cancel-membership-btn');
    if (cancelBtn) {
        cancelBtn.addEventListener('click', () => {
            cancelMembershipModal.show();
        });
    }

    const confirmCancelBtn = document.getElementById('confirm-cancel-btn');
    if (confirmCancelBtn) {
        confirmCancelBtn.addEventListener('click', () => {
            const body = new FormData();
            body.append('action', 'cancel');
            body.append('csrf_token', window.membershipConfig.csrfToken);

            fetch('../api/purchase_membership.php', {
                method: 'POST',
                body: body
            })
            .then(response => response.json())
            .then(data => {
                cancelMembershipModal.hide();
                showFeedback(data.message, data.success);
                if (data.success) {
                    setTimeout(() => location.reload(), 2000);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showFeedback('An error occurred.', false);
            });
        });
    }

    function showFeedback(message, success) {
        const toastEl = document.getElementById('feedback-toast');
        const toastBody = toastEl.querySelector('.toast-body');
        toastEl.classList.remove('bg-success', 'bg-danger');
        toastEl.classList.add(success ? 'bg-success' : 'bg-danger');
        toastBody.textContent = message;
        const toast = new bootstrap.Toast(toastEl);
        toast.show();
    }
});
