document.addEventListener('DOMContentLoaded', function () {
  let currentStep = 1;
  const totalSteps = 3;

  function showStep(step) {
    document.querySelectorAll('.step').forEach(function (el) {
      el.classList.remove('active');
      el.style.display = 'none';
    });
    const activeStep = document.querySelector('.step-' + step);
    if (activeStep) {
      activeStep.classList.add('active');
      activeStep.style.display = 'block';
    }

    const prevBtn = document.getElementById('prev-step');
    const nextBtn = document.getElementById('next-step');

    if (step === 1) {
      prevBtn.style.display = 'none';
      nextBtn.style.display = 'inline-block';
      nextBtn.textContent = 'Next';
    } else if (step === totalSteps) {
      prevBtn.style.display = 'inline-block';
      nextBtn.style.display = 'inline-block';
      nextBtn.textContent = 'Review & Confirm';
    } else {
      prevBtn.style.display = 'inline-block';
      nextBtn.style.display = 'inline-block';
      nextBtn.textContent = 'Next';
    }
  }

  showStep(currentStep);

  // Updated Next button click with email format validation
  document.getElementById('next-step').addEventListener('click', function () {
    let valid = true;
    const inputs = document.querySelectorAll('.step-' + currentStep + ' input[required], .step-' + currentStep + ' textarea[required]');

    function isValidEmail(email) {
      return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }
	  
	  for (let input of inputs) {
  const fieldId = input.id;
  const value = input.value.trim();

  if (input.type === 'checkbox' && !input.checked) {
    alert('Please check all required checkboxes.');
    valid = false;
    break;
  } else if (!value) {
    alert('Please fill all required fields.');
    valid = false;
    break;
  } else if ((fieldId === 'eco-email' || fieldId === 'email_s') && !isValidEmail(value)) {
    alert('Please enter a valid email.');
    valid = false;
    break;
  } else if (fieldId === 'eco-amount' && parseFloat(value) < 25) {
    alert('Price must be at least €25.');
    valid = false;
    break;
  }
}

 

    if (!valid) return;

    if (currentStep < totalSteps) {
      currentStep++;
      showStep(currentStep);
    } else {
      showSummaryModal();
    }
  });

  document.getElementById('prev-step').addEventListener('click', function () {
    if (currentStep > 1) {
      currentStep--;
      showStep(currentStep);
    }
  });

  document.querySelector('#eco-modal .close').addEventListener('click', function () {
    document.getElementById('eco-modal').style.display = 'none';
    document.querySelectorAll('.summary-info li.extra-info').forEach(p => p.remove());
  });

 function showSummaryModal() {
  const description = document.getElementById('disc-eco').value || '';
  const amount = parseFloat(document.getElementById('eco-amount').value) || 0;

  const sellerName = document.getElementById('seller_name').value || '';
  const sellerEmail = document.getElementById('email_s').value || '';
  const sellerIban = document.getElementById('seller_iban').value || '';
  const sellerReference = document.getElementById('seller_refer').value || '';

  const ecoName = document.getElementById('eco-name').value || '';
  const ecoEmail = document.getElementById('eco-email').value || '';

  // Handling Fee Calculation Based on Dynamic Admin Settings
  const feeType = ecoChequeData.fee_type || 'fixed';
  const feeValue = parseFloat(ecoChequeData.fee_value) || 0;
  const handlingFee = feeType === 'percentage' ? (amount * feeValue / 100) : feeValue;
  const total = amount + handlingFee;

  // Set data in the modal
  document.getElementById('summary-name').textContent = ecoName;
  document.getElementById('modal-summary-email').textContent = ecoEmail;
  document.getElementById('summary-amount').textContent = amount.toFixed(2);
  document.getElementById('summary-fee').textContent = handlingFee.toFixed(2);
  document.getElementById('summary-total').textContent = total.toFixed(2);

  document.querySelectorAll('.summary-info li.extra-info').forEach(p => p.remove());

  let extraInfoHtml = `
    <li class="extra-info">Description: ${description}</li>
    <li class="extra-info">Sold by: ${sellerName} - ${sellerEmail}</li>
    <li class="extra-info">Funds to be transferred to- BE${sellerIban}</li>`
 if ((sellerReference || '').trim() !== '') {
    extraInfoHtml += `<li class="extra-info">Under reference “${sellerReference}”</li>`;
  }
//     <li class="extra-info">Under reference “${sellerReference}”</li>
//   `;

  document.querySelector('.summary-info').insertAdjacentHTML('beforeend', extraInfoHtml);

  document.getElementById('eco-modal').style.display = 'flex';
}

  document.getElementById('submit-eco-payment').addEventListener('click', function () {
    const submitBtn = this;
    const postData = new FormData();
    postData.append('action', 'ecocheque_create_payment');
    postData.append('amount', document.getElementById('eco-amount').value);
    postData.append('eco_name', document.getElementById('eco-name').value);
    postData.append('name', document.getElementById('eco-name').value);
    postData.append('eco_email', document.getElementById('eco-email').value);
    postData.append('email', document.getElementById('eco-email').value);
    postData.append('description', document.getElementById('disc-eco').value);
    postData.append('seller_name', document.getElementById('seller_name').value);
    postData.append('seller_email', document.getElementById('email_s').value);
    postData.append('seller_iban', document.getElementById('seller_iban').value);
    postData.append('seller_reference', document.getElementById('seller_refer').value);

    submitBtn.disabled = true;
    submitBtn.textContent = 'Processing...';

    fetch(ecoChequeData.ajax_url, {
      method: 'POST',
      body: postData,
    })
      .then(response => response.json())
      .then(response => {
        if (response.success && response.data.checkout_url) {
          window.location.href = response.data.checkout_url;
        } else {
          alert('Payment failed: ' + (response.data.message || 'Unknown error'));
          submitBtn.disabled = false;
          submitBtn.textContent = 'Pay with EcoCheque';
        }
      })
      .catch(() => {
        alert('AJAX request failed.');
        submitBtn.disabled = false;
        submitBtn.textContent = 'Pay with EcoCheque';
      });
  });
});


document.addEventListener('DOMContentLoaded', function () {
    const ibanInput = document.getElementById('seller_iban');

    ibanInput.addEventListener('input', function () {
        let raw = ibanInput.value.replace(/[^A-Za-z0-9]/g, '').substring(0, 14);
        raw = raw.toUpperCase();
        let formatted = raw.replace(/(.{4})/g, '$1 ').trim();

        ibanInput.value = formatted;
    });
});

