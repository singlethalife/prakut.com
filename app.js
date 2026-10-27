const PRAKUT_SUPABASE_URL = 'https://pkzlfqyyaqxbngamxmks.supabase.co/';
const PRAKUT_SUPABASE_PUBLISHABLE_KEY = 'sb_publishable_j8rA90ajaAMUhPPlFm9TVA_Okno_2Tt';
const PRAKUT_RECAPTCHA_SITE_KEY = '6LcXs4stAAAAAGRsILEZ32u40VVVaULr95B6EpV_';
const PRAKUT_FORM_FUNCTION = 'submit-contact-form';

const PRAKUT_FORMS = {
  contactForm: { action: 'contact', formType: 'CONTACT_FORM' },
  auditForm: { action: 'audit', formType: 'FARM_HEALTH_AUDIT' },
  homeAuditForm: { action: 'homeAudit', formType: 'HOME_ASSESSMENT' },
  prakutOneForm: { action: 'prakutOne', formType: 'PRAKUT_ONE_INTEREST' },
  footerNewsletterForm: { action: 'newsletter', formType: 'NEWSLETTER_SIGNUP' }
};

let prakutSupabase = null;

function initializeSupabase() {
  if (typeof supabase === 'undefined') {
    console.error('Supabase JavaScript library has not been loaded.');
    return false;
  }
  if (!PRAKUT_SUPABASE_URL || PRAKUT_SUPABASE_URL === 'YOUR_SUPABASE_PROJECT_URL') {
    console.error('Supabase URL has not been configured.');
    return false;
  }
  if (!PRAKUT_SUPABASE_PUBLISHABLE_KEY || PRAKUT_SUPABASE_PUBLISHABLE_KEY === 'YOUR_SUPABASE_PUBLISHABLE_KEY') {
    console.error('Supabase publishable key has not been configured.');
    return false;
  }
  const { createClient } = supabase;
  prakutSupabase = createClient(PRAKUT_SUPABASE_URL, PRAKUT_SUPABASE_PUBLISHABLE_KEY);
  return true;
}

function setupMobileNavigation() {
  const toggle = document.getElementById('navToggle');
  const links = document.getElementById('navLinks');
  if (!toggle || !links) return;
  toggle.addEventListener('click', function () {
    const open = links.classList.toggle('is-open');
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
  });
  links.querySelectorAll('a').forEach(function (link) {
    link.addEventListener('click', function () {
      links.classList.remove('is-open');
      toggle.setAttribute('aria-expanded', 'false');
    });
  });
}

function setupScrollReveal() {
  if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.15 });
    document.querySelectorAll('.reveal').forEach(function (element) {
      observer.observe(element);
    });
  } else {
    document.querySelectorAll('.reveal').forEach(function (element) {
      element.classList.add('is-visible');
    });
  }
}

function setupTransformationPanel() {
  const panel = document.querySelector('[data-transformation-panel]');
  if (!panel) return;
  const description = panel.querySelector('.chain__description');
  const buttons = panel.querySelectorAll('.chain__button');
  buttons.forEach(function (button) {
    if (button.getAttribute('aria-selected') === 'true') {
      button.closest('.chain__item').classList.add('is-active');
    }
  });
  buttons.forEach(function (button) {
    button.addEventListener('click', function () {
      buttons.forEach(function (item) {
        item.closest('.chain__item').classList.remove('is-active');
        item.setAttribute('aria-selected', 'false');
      });
      button.closest('.chain__item').classList.add('is-active');
      button.setAttribute('aria-selected', 'true');
      description.textContent = button.dataset.description;
    });
  });
}

function setupTransformationTimeline() {
  const stages = document.querySelectorAll('.timeline__stage');
  const image = document.querySelector('[data-timeline-image]');
  const description = document.querySelector('[data-timeline-description]');
  const heading = document.querySelector('.timeline-detail .eyebrow');
  if (!stages.length || !image || !description || !heading) return;
  const details = {
    land: ['01 / Land', 'Assess land quality, utilisation, irrigation and long-term farm potential.', 'assets/stage-land.jpg'],
    operations: ['02 / Operations', 'Improve farm workflows, resource utilisation and day-to-day efficiency.', 'assets/stage-operations.jpg'],
    people: ['03 / People', 'Improve workforce management, roles, responsibilities and accountability.', 'assets/stage-people.jpg'],
    data: ['04 / Data', 'Use farm data to make better operational and financial decisions.', 'assets/stage-data.jpg'],
    crops: ['05 / Crops', 'Improve crop planning, productivity and farm output.', 'assets/stage-crops.jpg'],
    revenue: ['06 / Revenue', 'Identify opportunities to improve revenue and long-term profitability.', 'assets/stage-revenue.jpg']
  };
  stages.forEach(function (stage) {
    stage.addEventListener('click', function () {
      const detail = details[stage.dataset.stage];
      stages.forEach(function (item) {
        item.classList.remove('is-active');
        item.setAttribute('aria-selected', 'false');
      });
      stage.classList.add('is-active');
      stage.setAttribute('aria-selected', 'true');
      heading.textContent = detail[0];
      description.textContent = detail[1];
      image.src = detail[2];
      image.alt = detail[0].split(' / ')[1] + ' transformation';
    });
  });
}

function setupCounters() {
  const counters = document.querySelectorAll('[data-counter]');
  if (!counters.length) return;
  const animateCounter = function (counter) {
    const target = Number(counter.dataset.counter);
    const duration = 1100;
    const start = performance.now();
    const update = function (time) {
      const progress = Math.min((time - start) / duration, 1);
      counter.textContent = String(Math.floor(progress * target));
      if (progress < 1) window.requestAnimationFrame(update);
    };
    window.requestAnimationFrame(update);
  };
  if (!('IntersectionObserver' in window)) {
    counters.forEach(function (counter) { counter.textContent = counter.dataset.counter; });
    return;
  }
  const observer = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (entry.isIntersecting) {
        animateCounter(entry.target);
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.6 });
  counters.forEach(function (counter) { observer.observe(counter); });
}

function setupActiveNavigation() {
  const currentPage = location.pathname.split('/').pop() || 'index.html';
  document.querySelectorAll('.nav__links a').forEach(function (link) {
    if (link.getAttribute('href') === currentPage) {
      link.classList.add('is-active');
    }
  });
}

async function getRecaptchaToken(action) {
  if (typeof grecaptcha === 'undefined') {
    throw new Error('Security verification is unavailable. Please refresh the page and try again.');
  }
  if (!grecaptcha.enterprise) {
    throw new Error('reCAPTCHA Enterprise is unavailable. Please refresh the page and try again.');
  }
  await new Promise(function (resolve) {
    grecaptcha.enterprise.ready(function () {
      resolve();
    });
  });
  const token = await grecaptcha.enterprise.execute(PRAKUT_RECAPTCHA_SITE_KEY, { action: action });
  if (!token) {
    throw new Error('Security verification failed. Please try again.');
  }
  return token;
}

function collectFormData(form) {
  const fields = {};
  new FormData(form).forEach(function (value, key) {
    if (key === 'g-recaptcha-response' || value instanceof File) return;
    fields[key] = String(value);
  });
  return fields;
}

function getFormStatusElement(form) {
  let status = form.querySelector('#formMessage');
  if (!status) status = form.querySelector('.form-note');
  if (!status) {
    status = document.createElement('p');
    status.className = 'form-note';
    form.appendChild(status);
  }
  return status;
}

function setFormMessage(form, message) {
  getFormStatusElement(form).textContent = message;
}

function showFormError(form, message) {
  const status = getFormStatusElement(form);
  status.textContent = message;
  status.classList.add('form-error');
}

function clearFormError(form) {
  getFormStatusElement(form).classList.remove('form-error');
}

function getSubmitButton(form) {
  return form.querySelector('button[type="submit"]');
}

function setButtonProcessing(button, text) {
  if (!button) return;
  if (!button.dataset.originalText) button.dataset.originalText = button.textContent;
  button.disabled = true;
  button.textContent = text;
}

function resetButton(button) {
  if (!button) return;
  button.disabled = false;
  button.textContent = button.dataset.originalText || 'Submit';
}

function showFormSuccess(form) {
  const button = getSubmitButton(form);
  const originalText = button && (button.dataset.originalText || button.textContent);
  clearFormError(form);
  setFormMessage(form, "Thank you. We've received your details and will get back to you shortly.");
  form.reset();
  if (button) {
    button.disabled = true;
    button.textContent = "✓ Thanks! We'll be in touch.";
  }
  setTimeout(function () {
    if (!button) return;
    button.textContent = originalText || 'Submit';
    button.disabled = false;
  }, 4000);
}

async function submitPrakutForm(form, config) {
  const button = getSubmitButton(form);
  if (button && button.disabled) return;
  if (!form.checkValidity()) {
    form.reportValidity();
    return;
  }
  try {
    clearFormError(form);
    setButtonProcessing(button, 'Verifying...');
    setFormMessage(form, 'Verifying your submission...');
    let recaptchaToken = null;
    try { recaptchaToken = await getRecaptchaToken(config.action); } catch (e) {}
    const requestBody = {
      form_type: config.formType,
      action: config.action,
      source_page: window.location.pathname,
      fields: collectFormData(form),
      recaptcha_token: recaptchaToken
    };
    console.log('Submitting form to backend:', config.formType);
    setButtonProcessing(button, 'Submitting...');
    const response = await fetch('api_enquiry.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(requestBody)
    });
    const data = await response.json();
    if (!response.ok || !data || data.success !== true) {
      console.error('Form rejected by backend:', data);
      throw new Error(data && (data.error || data.message) ? (data.error || data.message) : 'Your submission could not be verified.');
    }
    console.log('Form submission successful:', data);
    showFormSuccess(form);
  } catch (error) {
    console.error('Prakut form submission error:', error);
    showFormError(form, error.message || 'Something went wrong. Please try again.');
    resetButton(button);
  }
}

function setupFormHandlers() {
  Object.keys(PRAKUT_FORMS).forEach(function (formId) {
    const form = document.getElementById(formId);
    if (!form) return;
    const config = PRAKUT_FORMS[formId];
    const button = getSubmitButton(form);
    if (button && !button.dataset.originalText) {
      button.dataset.originalText = button.textContent;
    }
    form.onsubmit = null;
    form.addEventListener('submit', function (event) {
      event.preventDefault();
      submitPrakutForm(form, config);
    });
    console.log('Prakut form initialized:', formId, config.formType);
  });
}

document.addEventListener('DOMContentLoaded', function () {
  initializeSupabase();
  setupMobileNavigation();
  setupScrollReveal();
  setupTransformationPanel();
  setupTransformationTimeline();
  setupCounters();
  setupActiveNavigation();
  setupFormHandlers();
});
