(function () {
  'use strict';

  var ENDPOINT = 'api/submit-lead.php';
  var ITI_UTILS = 'https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/18.2.1/js/utils.js';
  var NAME_RE = /^[\p{L}][\p{L}\p{M}' .\-]{0,29}$/u;
  var EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
  var CLICK_ID_RE = /^[A-Za-z0-9._\-]{1,128}$/;
  var PHONE_COUNTRY = { it: 'it', es: 'es', en: 'gb' };

  var MESSAGES = {
    first_name: 'Inserisci un nome valido.',
    last_name: 'Inserisci un cognome valido.',
    email: 'Inserisci un indirizzo email valido.',
    generic: 'Si è verificato un errore. Riprova più tardi.'
  };

  function getClickId() {
    var value = new URLSearchParams(window.location.search).get('click_id') || '';
    return CLICK_ID_RE.test(value) ? value : '';
  }

  function setError(form, key, message) {
    var el = form.querySelector('[data-error="error-' + key + '"]');
    if (!el) return;
    el.textContent = message ? I18N.t(message) : '';
    el.style.display = message ? 'block' : 'none';
  }

  function clearErrors(form) {
    form.querySelectorAll('[data-error]').forEach(function (el) {
      el.textContent = '';
      el.style.display = 'none';
    });
  }

  function setLoading(form, button, loading) {
    var preloader = form.querySelector('[data-input="reg-form__preloader"]');
    if (preloader) preloader.style.display = loading ? 'flex' : 'none';
    button.disabled = loading;
  }

  function isSafeRedirect(url) {
    try {
      return new URL(url).protocol === 'https:';
    } catch (e) {
      return false;
    }
  }

  function initForm(form) {
    var firstname = form.querySelector('[data-input="reg-form-firstname"]');
    var lastname = form.querySelector('[data-input="reg-form-lastname"]');
    var email = form.querySelector('[data-input="reg-form-email"]');
    var phone = form.querySelector('[data-input="reg-form-phone"]');
    var honeypot = form.querySelector('[name="hp_extra_field"]');
    var button = form.querySelector('[data-input="do-registration"]');
    if (!firstname || !lastname || !email || !phone || !button) return;

    var iti = window.intlTelInput(phone, {
      initialCountry: PHONE_COUNTRY[I18N.lang],
      // Only Italian, Spanish and UK mobile numbers are accepted.
      onlyCountries: ['it', 'es', 'gb'],
      preferredCountries: [],
      separateDialCode: true,
      utilsScript: ITI_UTILS
    });

    // Phone country follows the selected language until the visitor types a number
    I18N.onChange(function (lang) {
      if (!phone.value.trim()) iti.setCountry(PHONE_COUNTRY[lang]);
    });

    var submitting = false;

    // Our own IT/ES/GB mobile rules (phone-check.js), so the check never
    // depends on intl-tel-input's utils.js having loaded from the CDN.
    function checkPhone() {
      var selected = (iti.getSelectedCountryData().iso2 || 'it').toUpperCase();
      return PhoneCheck.check(phone.value, selected);
    }

    function validate() {
      var errors = {};
      if (!NAME_RE.test(firstname.value.trim())) errors.first_name = MESSAGES.first_name;
      if (!NAME_RE.test(lastname.value.trim())) errors.last_name = MESSAGES.last_name;
      if (!EMAIL_RE.test(email.value.trim())) errors.email = MESSAGES.email;
      var phoneCheck = checkPhone();
      if (!phoneCheck.ok) errors.phone_number = PhoneCheck.MESSAGES[phoneCheck.reason];
      return errors;
    }

    function submit(event) {
      if (event) event.preventDefault();
      if (submitting) return;

      clearErrors(form);
      var errors = validate();
      var keys = Object.keys(errors);
      if (keys.length) {
        keys.forEach(function (k) { setError(form, k, errors[k]); });
        return;
      }

      submitting = true;
      setLoading(form, button, true);

      fetch(ENDPOINT, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({
          firstname: firstname.value.trim(),
          lastname: lastname.value.trim(),
          email: email.value.trim(),
          mobile: checkPhone().e164, // E.164, e.g. +393478129054
          click_id: getClickId(),
          website: honeypot ? honeypot.value : ''
        })
      })
        .then(function (res) {
          return res.json().catch(function () { return {}; });
        })
        .then(function (data) {
          if (data && data.success === true && isSafeRedirect(data.autologin_url)) {
            window.location.assign(data.autologin_url);
            return; // keep the preloader visible while navigating
          }
          var fieldErrors = (data && data.errors) || {};
          var fieldKeys = Object.keys(fieldErrors);
          if (fieldKeys.length) {
            fieldKeys.forEach(function (k) { setError(form, k, String(fieldErrors[k])); });
          } else {
            setError(form, 'country_iso_code', (data && data.message) || MESSAGES.generic);
          }
          submitting = false;
          setLoading(form, button, false);
        })
        .catch(function () {
          setError(form, 'country_iso_code', MESSAGES.generic);
          submitting = false;
          setLoading(form, button, false);
        });
    }

    button.addEventListener('click', submit);
    form.addEventListener('submit', submit); // Enter key inside an input
  }

  function init() {
    if (typeof window.intlTelInput !== 'function') return;
    document.querySelectorAll('form.reg-form').forEach(initForm);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
