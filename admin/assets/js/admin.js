// Wires each row's Send buttons to admin/send-link.php.
// One event listener on the table (delegation) rather than one per button,
// since rows are static here but this scales fine if the table is ever re-rendered.

document.querySelectorAll('.loans-table tbody tr').forEach((row) => {
  const loanId = row.dataset.loanId;
  const resultEl = row.querySelector('.send-result');
  const buttons = row.querySelectorAll('.send-btn');

  buttons.forEach((btn) => {
    btn.addEventListener('click', async () => {
      const channel = btn.dataset.channel;
      setButtonsDisabled(buttons, true);
      setResult(resultEl, '', '');

      try {
        const res = await fetch('send-link.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            loan_id: Number(loanId),
            channel,
            csrf_token: window.CSRF_TOKEN,
          }),
        });

        const data = await res.json().catch(() => null);

        if (res.ok && data?.ok && !data.partial) {
          setResult(resultEl, 'success', `Sent via ${data.sent_via}.`, data.pay_url);
        } else if (data?.ok && data.partial) {
          setResult(resultEl, 'success', `Partially sent — ${data.errors.join('; ')}`, data.pay_url);
        } else {
          const msg = data?.error || (data?.errors ? data.errors.join('; ') : 'Failed to send.');
          // pay_url still comes back on failure (e.g. SMS/WhatsApp not configured yet) —
          // show it anyway so you can copy it and test pay.php by hand.
          setResult(resultEl, 'error', msg, data?.pay_url);
        }
      } catch (err) {
        setResult(resultEl, 'error', 'Network error — please try again.');
      } finally {
        setButtonsDisabled(buttons, false);
      }
    });
  });
});

const table = document.querySelector('.loans-table');
const bulkToolbar = document.querySelector('.bulk-toolbar');
const selectAllCheckbox = document.querySelector('.select-all-checkbox');
const bulkCheckboxes = [...document.querySelectorAll('.loan-checkbox')];
const bulkButtons = [...document.querySelectorAll('.bulk-send-btn')];
const bulkClearButton = document.querySelector('.bulk-clear-btn');
const bulkCount = document.querySelector('.bulk-selection-count');
const bulkResult = document.querySelector('.bulk-result');

function selectedLoanIds() {
  return bulkCheckboxes
    .filter((checkbox) => checkbox.checked)
    .map((checkbox) => Number(checkbox.closest('tr').dataset.loanId));
}

function updateBulkToolbar() {
  const selectedCount = selectedLoanIds().length;
  bulkToolbar.hidden = selectedCount === 0;
  bulkCount.textContent = `${selectedCount} selected`;
  bulkButtons.forEach((button) => { button.disabled = selectedCount === 0; });

  if (selectAllCheckbox) {
    selectAllCheckbox.checked = selectedCount > 0 && selectedCount === bulkCheckboxes.length;
    selectAllCheckbox.indeterminate = selectedCount > 0 && selectedCount < bulkCheckboxes.length;
  }
}

bulkCheckboxes.forEach((checkbox) => checkbox.addEventListener('change', updateBulkToolbar));

selectAllCheckbox?.addEventListener('change', () => {
  bulkCheckboxes.forEach((checkbox) => { checkbox.checked = selectAllCheckbox.checked; });
  updateBulkToolbar();
});

bulkClearButton?.addEventListener('click', () => {
  bulkCheckboxes.forEach((checkbox) => { checkbox.checked = false; });
  bulkResult.textContent = '';
  updateBulkToolbar();
});

bulkButtons.forEach((button) => {
  button.addEventListener('click', async () => {
    const loanIds = selectedLoanIds();
    const channel = button.dataset.channel;

    if (!loanIds.length || !confirm(`Send ${channel} payment links to ${loanIds.length} selected loan${loanIds.length === 1 ? '' : 's'}?`)) {
      return;
    }

    setButtonsDisabled(bulkButtons, true);
    bulkClearButton.disabled = true;
    bulkResult.className = 'bulk-result';
    bulkResult.textContent = 'Sending…';

    try {
      const res = await fetch('send-bulk-links.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          loan_ids: loanIds,
          channel,
          csrf_token: window.CSRF_TOKEN,
        }),
      });
      const data = await res.json().catch(() => null);

      if (data?.results) {
        data.results.forEach((item) => {
          const row = document.querySelector(`tr[data-loan-id="${item.loan_id}"]`);
          if (row) {
            setResult(row.querySelector('.send-result'), item.ok ? 'success' : 'error', item.ok ? `Sent via ${item.sent_via}.` : item.error);
          }
        });
      }

      if (data?.ok) {
        bulkResult.className = 'bulk-result success';
        bulkResult.textContent = `Sent ${data.sent} of ${loanIds.length}.`;
        bulkCheckboxes.forEach((checkbox) => { checkbox.checked = false; });
      } else {
        bulkResult.className = 'bulk-result error';
        bulkResult.textContent = data?.error || `Sent ${data?.sent || 0} of ${loanIds.length}; ${data?.failed || loanIds.length} failed.`;
      }
    } catch (err) {
      bulkResult.className = 'bulk-result error';
      bulkResult.textContent = 'Network error — please try again.';
    } finally {
      bulkClearButton.disabled = false;
      updateBulkToolbar();
    }
  });
});

updateBulkToolbar();

function setButtonsDisabled(buttons, disabled) {
  buttons.forEach((b) => { b.disabled = disabled; });
}

function setResult(el, kind, message, payUrl) {
  el.innerHTML = '';
  el.className = `send-result ${kind}`;
  el.appendChild(document.createTextNode(message));

  if (payUrl) {
    const link = document.createElement('a');
    link.href = payUrl;
    link.textContent = 'Open link';
    link.target = '_blank';
    link.rel = 'noopener';
    link.style.marginLeft = '6px';

    const copyBtn = document.createElement('button');
    copyBtn.type = 'button';
    copyBtn.textContent = 'Copy';
    copyBtn.className = 'copy-link-btn';
    copyBtn.addEventListener('click', () => {
      navigator.clipboard.writeText(payUrl).then(() => {
        copyBtn.textContent = 'Copied';
        setTimeout(() => { copyBtn.textContent = 'Copy'; }, 1500);
      });
    });

    el.appendChild(link);
    el.appendChild(copyBtn);
  }
}
