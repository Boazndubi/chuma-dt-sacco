const form = document.getElementById('pay-form');
const button = document.getElementById('pay-button');
const statusEl = document.getElementById('pay-status');

if (form) {
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    setStatus('pending', 'Sending payment request to your phone…');
    button.disabled = true;

    try {
      const res = await fetch('api/stk-push.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          token: form.token.value,
          product_code: form.product_code.value,
          member_no: form.member_no.value,
          phone: form.phone.value,
        }),
      });

      const data = await res.json();

      if (!res.ok || !data.checkout_request_id) {
        setStatus('error', data.error || 'Could not start the payment. Please try again.');
        button.disabled = false;
        return;
      }

      setStatus('pending', 'Check your phone and enter your M-Pesa PIN to confirm.');
      pollStatus(data.checkout_request_id);
    } catch (err) {
      setStatus('error', 'Network error. Please check your connection and try again.');
      button.disabled = false;
    }
  });
}

async function pollStatus(checkoutRequestId, attempt = 0) {
  if (attempt > 20) {
    setStatus('error', "We haven't heard back yet. If money left your account, it will still be recorded.");
    button.disabled = false;
    return;
  }

  await new Promise((r) => setTimeout(r, 3000));

  const res = await fetch(`api/check-status.php?checkout_request_id=${encodeURIComponent(checkoutRequestId)}`);
  const data = await res.json();

  if (data.status === 'success') {
    setStatus('success', `Payment received. Receipt: ${data.mpesa_receipt}`);
    button.disabled = true;
    button.textContent = 'Paid';
    return;
  }

  if (data.status === 'failed' || data.status === 'cancelled') {
    setStatus('error', data.result_desc || 'Payment was not completed.');
    button.disabled = false;
    return;
  }

  pollStatus(checkoutRequestId, attempt + 1);
}

function setStatus(kind, message) {
  statusEl.textContent = message;
  statusEl.className = `status ${kind}`;
}
