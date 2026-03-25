import './bootstrap';

document.documentElement.classList.add('js-ready');

document.addEventListener('DOMContentLoaded', () => {
    const copyText = async (value) => {
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(value);

            return;
        }

        const fallbackField = document.createElement('textarea');

        fallbackField.value = value;
        fallbackField.setAttribute('readonly', 'readonly');
        fallbackField.style.position = 'fixed';
        fallbackField.style.top = '-9999px';
        fallbackField.style.left = '-9999px';

        document.body.appendChild(fallbackField);
        fallbackField.select();
        fallbackField.setSelectionRange(0, fallbackField.value.length);

        const didCopy = document.execCommand('copy');

        document.body.removeChild(fallbackField);

        if (!didCopy) {
            throw new Error('Copy failed');
        }
    };

    const revealItems = document.querySelectorAll('[data-reveal]');

    if (revealItems.length > 0 && 'IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        }, {
            rootMargin: '0px 0px 120px 0px',
            threshold: 0.05,
        });

        revealItems.forEach((item, index) => {
            item.style.setProperty('--reveal-delay', `${Math.min(index * 70, 280)}ms`);
            observer.observe(item);
        });
    }

    const form = document.querySelector('[data-generate-form]');
    const button = form?.querySelector('[data-submit-button]');
    const label = form?.querySelector('[data-submit-label]');
    const defaultGenerateLabel = label?.textContent ?? '';

    const setGenerateBusyState = (isBusy) => {
        if (!button || !label) {
            return;
        }

        if (isBusy) {
            button.setAttribute('disabled', 'disabled');
            button.classList.add('is-busy');
            label.textContent = 'Generating article...';

            return;
        }

        button.removeAttribute('disabled');
        button.classList.remove('is-busy');
        label.textContent = defaultGenerateLabel;
    };

    form?.addEventListener('submit', () => {
        setGenerateBusyState(true);
    });

    const recaptchaSiteKey = document.body.dataset.recaptchaSiteKey?.trim() ?? '';
    const recaptchaForms = document.querySelectorAll('[data-recaptcha-form]');

    if (recaptchaSiteKey !== '' && recaptchaForms.length > 0) {
        const setRecaptchaMessage = (targetForm, message = '', state = '') => {
            const messageNode = targetForm.querySelector('[data-recaptcha-message]');

            if (!(messageNode instanceof HTMLElement)) {
                return;
            }

            messageNode.textContent = message;
            messageNode.classList.remove('is-success', 'is-error', 'is-pending');

            if (state !== '') {
                messageNode.classList.add(state);
            }
        };

        const setSubmitButtonsDisabled = (targetForm, disabled) => {
            targetForm.querySelectorAll('button[type="submit"]').forEach((submitButton) => {
                if (!(submitButton instanceof HTMLButtonElement)) {
                    return;
                }

                if (disabled) {
                    submitButton.setAttribute('disabled', 'disabled');
                } else if (submitButton !== button) {
                    submitButton.removeAttribute('disabled');
                }
            });
        };

        const executeRecaptcha = (action) => new Promise((resolve, reject) => {
            if (!window.grecaptcha?.enterprise) {
                reject(new Error('reCAPTCHA unavailable'));

                return;
            }

            window.grecaptcha.enterprise.ready(async () => {
                try {
                    const token = await window.grecaptcha.enterprise.execute(recaptchaSiteKey, { action });

                    resolve(token);
                } catch (error) {
                    reject(error);
                }
            });
        });

        recaptchaForms.forEach((protectedForm) => {
            if (!(protectedForm instanceof HTMLFormElement)) {
                return;
            }

            protectedForm.addEventListener('submit', async (event) => {
                if (protectedForm.dataset.recaptchaVerified === 'true') {
                    return;
                }

                event.preventDefault();

                if (protectedForm.dataset.recaptchaSubmitting === 'true') {
                    return;
                }

                const action = protectedForm.dataset.recaptchaAction ?? '';
                const tokenField = protectedForm.querySelector('[data-recaptcha-token]');

                if (action === '' || !(tokenField instanceof HTMLInputElement)) {
                    protectedForm.submit();

                    return;
                }

                protectedForm.dataset.recaptchaSubmitting = 'true';
                setSubmitButtonsDisabled(protectedForm, true);
                setRecaptchaMessage(protectedForm);

                try {
                    const token = await executeRecaptcha(action);

                    tokenField.value = token;
                    protectedForm.dataset.recaptchaVerified = 'true';

                    if (protectedForm === form) {
                        setGenerateBusyState(true);
                    }

                    protectedForm.submit();
                } catch (error) {
                    delete protectedForm.dataset.recaptchaSubmitting;
                    setSubmitButtonsDisabled(protectedForm, false);
                    setRecaptchaMessage(
                        protectedForm,
                        'Security check could not be completed. Please try again.',
                        'is-error',
                    );

                    if (protectedForm === form) {
                        setGenerateBusyState(false);
                    }
                }
            });
        });
    }

    const passwordToggles = document.querySelectorAll('[data-password-toggle]');

    passwordToggles.forEach((toggle) => {
        toggle.addEventListener('click', () => {
            const targetId = toggle.getAttribute('data-password-toggle');
            const input = targetId ? document.getElementById(targetId) : null;

            if (!(input instanceof HTMLInputElement)) {
                return;
            }

            const shouldReveal = input.type === 'password';

            input.type = shouldReveal ? 'text' : 'password';
            toggle.classList.toggle('is-visible', shouldReveal);
            toggle.setAttribute('aria-pressed', String(shouldReveal));
            toggle.setAttribute('aria-label', shouldReveal ? 'Hide password' : 'Show password');
        });
    });

    const usernameInput = document.querySelector('[data-username-input]');
    const usernameStatus = document.querySelector('[data-username-status]');

    if (usernameInput instanceof HTMLInputElement && usernameStatus instanceof HTMLElement) {
        let debounceTimer;
        let activeController = null;
        let latestLookupValue = usernameInput.value.trim().toLowerCase();

        const applyUsernameStatus = (message, state = '') => {
            usernameStatus.textContent = message;
            usernameStatus.classList.remove('is-success', 'is-error', 'is-pending');

            if (state !== '') {
                usernameStatus.classList.add(state);
            }
        };

        const lookupUsername = (rawValue) => {
            const username = rawValue.trim().toLowerCase();
            latestLookupValue = username;

            if (activeController) {
                activeController.abort();
            }

            if (username.length === 0) {
                applyUsernameStatus('');
                return;
            }

            if (username.length < 3) {
                applyUsernameStatus('Enter at least 3 characters.', 'is-error');
                return;
            }

            applyUsernameStatus('Checking availability...', 'is-pending');

            const checkUrl = usernameInput.dataset.usernameCheckUrl;

            if (!checkUrl) {
                return;
            }

            activeController = new AbortController();

            const url = new URL(checkUrl, window.location.origin);
            url.searchParams.set('username', username);

            fetch(url, {
                headers: {
                    Accept: 'application/json',
                },
                signal: activeController.signal,
            })
                .then((response) => response.ok ? response.json() : Promise.reject(new Error('Request failed')))
                .then((payload) => {
                    if (usernameInput.value.trim().toLowerCase() !== latestLookupValue) {
                        return;
                    }

                    applyUsernameStatus(
                        payload.message ?? '',
                        payload.available ? 'is-success' : 'is-error',
                    );
                })
                .catch((error) => {
                    if (error.name === 'AbortError') {
                        return;
                    }

                    applyUsernameStatus('Could not check availability right now.', 'is-error');
                });
        };

        usernameInput.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            debounceTimer = window.setTimeout(() => {
                lookupUsername(usernameInput.value);
            }, 250);
        });

        if (usernameInput.value.trim() !== '' && usernameStatus.textContent.trim() === '') {
            lookupUsername(usernameInput.value);
        }
    }

    const passwordInput = document.querySelector('[data-password-input]');
    const passwordStatus = document.querySelector('[data-password-status]');
    const passwordRules = document.querySelectorAll('[data-password-rule]');

    if (
        passwordInput instanceof HTMLInputElement
        && passwordStatus instanceof HTMLElement
        && passwordRules.length > 0
    ) {
        const rules = {
            length: (value) => value.length >= 8,
            uppercase: (value) => /[A-Z]/.test(value),
            number: (value) => /\d/.test(value),
            symbol: (value) => /[^A-Za-z0-9]/.test(value),
        };
        const hadServerError = passwordStatus.classList.contains('is-error');
        let hasInteracted = false;

        const updatePasswordFeedback = () => {
            const value = passwordInput.value;
            const hasValue = value.length > 0;
            let allCriteriaMet = true;

            passwordRules.forEach((rule) => {
                const key = rule.getAttribute('data-password-rule');
                const isMet = key && key in rules ? rules[key](value) : false;

                allCriteriaMet &&= isMet;

                rule.classList.remove('is-met', 'is-unmet');

                if (hasValue) {
                    rule.classList.add(isMet ? 'is-met' : 'is-unmet');
                }
            });

            passwordStatus.classList.remove('is-success', 'is-error', 'is-pending');

            if (!hasValue) {
                if (!hasInteracted && hadServerError) {
                    passwordStatus.classList.add('is-error');
                    return;
                }

                passwordStatus.textContent = 'Use at least 8 characters with an uppercase letter, number, and symbol.';
                return;
            }

            if (allCriteriaMet) {
                passwordStatus.textContent = 'Password meets all requirements.';
                passwordStatus.classList.add('is-success');

                return;
            }

            passwordStatus.textContent = 'Password must meet all of the criteria below.';
            passwordStatus.classList.add('is-error');
        };

        passwordInput.addEventListener('input', () => {
            hasInteracted = true;
            updatePasswordFeedback();
        });

        updatePasswordFeedback();
    }

    const shareCopyButtons = document.querySelectorAll('[data-share-copy]');
    const relatedPostGrid = document.querySelector('[data-related-post-grid]');
    const relatedLoadButton = document.querySelector('[data-related-load-more]');
    const relatedStatus = document.querySelector('[data-related-status]');
    const homePostGrid = document.querySelector('[data-home-post-grid]');
    const homeLoadButton = document.querySelector('[data-home-load-more]');
    const homeStatus = document.querySelector('[data-home-status]');

    const setupRowLink = (row) => {
        if (!(row instanceof HTMLElement)) {
            return;
        }

        if (row.dataset.rowLinkBound === 'true') {
            return;
        }

        const href = row.dataset.rowLink;

        if (!href) {
            return;
        }

        row.dataset.rowLinkBound = 'true';

        const isIgnoredTarget = (target) => {
            if (!(target instanceof HTMLElement)) {
                return false;
            }

            return target.closest('[data-row-link-ignore], a, button, input, select, textarea, label, form') !== null;
        };

        const navigateToRowLink = (event) => {
            if (event.metaKey || event.ctrlKey) {
                window.open(href, '_blank', 'noopener');

                return;
            }

            if (event.shiftKey) {
                window.open(href, '_blank', 'noopener');

                return;
            }

            window.location.assign(href);
        };

        row.addEventListener('click', (event) => {
            if (event.defaultPrevented || event.button !== 0 || isIgnoredTarget(event.target)) {
                return;
            }

            const selectedText = window.getSelection?.()?.toString() ?? '';

            if (selectedText !== '') {
                return;
            }

            navigateToRowLink(event);
        });

        row.addEventListener('keydown', (event) => {
            if (event.target !== row || (event.key !== 'Enter' && event.key !== ' ')) {
                return;
            }

            event.preventDefault();
            navigateToRowLink(event);
        });
    };

    document.querySelectorAll('[data-row-link]').forEach(setupRowLink);

    const setupLoadMoreButton = ({
        button,
        grid,
        status,
        itemSingular,
        itemPlural,
        urlAttribute,
        afterAppend = () => {},
    }) => {
        if (
            !(grid instanceof HTMLElement)
            || !(button instanceof HTMLButtonElement)
            || !(status instanceof HTMLElement)
        ) {
            return;
        }

        const defaultLoadMoreLabel = button.textContent.trim();
        let isLoadingItems = false;

        button.addEventListener('click', async () => {
            if (isLoadingItems) {
                return;
            }

            const listUrl = button.dataset[urlAttribute];
            const nextPage = Number.parseInt(button.dataset.nextPage ?? '', 10);

            if (!listUrl || Number.isNaN(nextPage) || nextPage < 1) {
                return;
            }

            isLoadingItems = true;
            button.disabled = true;
            button.textContent = 'Loading...';
            status.textContent = '';
            status.classList.remove('is-success', 'is-error');

            try {
                const url = new URL(listUrl, window.location.origin);

                url.searchParams.set('page', String(nextPage));

                const response = await fetch(url, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!response.ok) {
                    throw new Error('Request failed');
                }

                const payload = await response.json();
                const html = typeof payload.html === 'string' ? payload.html.trim() : '';
                const appendedCount = Number.isInteger(payload.count) ? payload.count : 0;

                if (html !== '') {
                    grid.insertAdjacentHTML('beforeend', html);
                    afterAppend();
                }

                const itemLabel = appendedCount === 1 ? itemSingular : itemPlural;

                if (payload.has_more && Number.isInteger(payload.next_page)) {
                    button.dataset.nextPage = String(payload.next_page);
                    button.disabled = false;
                    button.textContent = defaultLoadMoreLabel;

                    if (appendedCount > 0) {
                        status.textContent = `Loaded ${appendedCount} more ${itemLabel}.`;
                        status.classList.add('is-success');
                    }
                } else {
                    button.textContent = defaultLoadMoreLabel;
                    button.disabled = true;
                    button.hidden = true;
                    status.textContent = appendedCount > 0
                        ? `Loaded the final ${appendedCount} ${itemLabel}.`
                        : `No more ${itemPlural} to load.`;
                    status.classList.add('is-success');
                }
            } catch (error) {
                button.disabled = false;
                button.textContent = defaultLoadMoreLabel;
                status.textContent = `Could not load more ${itemPlural} right now.`;
                status.classList.add('is-error');
            } finally {
                isLoadingItems = false;
            }
        });
    };

    setupLoadMoreButton({
        button: relatedLoadButton,
        grid: relatedPostGrid,
        status: relatedStatus,
        itemSingular: 'article',
        itemPlural: 'articles',
        urlAttribute: 'relatedUrl',
    });

    setupLoadMoreButton({
        button: homeLoadButton,
        grid: homePostGrid,
        status: homeStatus,
        itemSingular: 'post',
        itemPlural: 'posts',
        urlAttribute: 'homeUrl',
        afterAppend: () => {
            homePostGrid.querySelectorAll('[data-row-link]').forEach(setupRowLink);
        },
    });

    shareCopyButtons.forEach((button) => {
        if (!(button instanceof HTMLButtonElement)) {
            return;
        }

        button.addEventListener('click', async () => {
            const card = button.closest('[data-share-card]');
            const status = card?.querySelector('[data-share-status]');
            const platform = button.getAttribute('data-share-copy');
            const shareUrl = card?.getAttribute('data-share-url')?.trim() ?? '';
            let shareText = '';
            let successMessage = 'Share text copied.';

            if (!(card instanceof HTMLElement) || !(status instanceof HTMLElement) || !platform || !shareUrl) {
                return;
            }

            status.classList.remove('is-success', 'is-error');

            if (platform === 'link') {
                shareText = shareUrl;
                successMessage = 'Article link copied.';
            }

            if (shareText === '') {
                status.textContent = 'Unsupported share option.';
                status.classList.add('is-error');

                return;
            }

            try {
                await copyText(shareText);
                status.textContent = successMessage;
                status.classList.add('is-success');
            } catch (error) {
                status.textContent = 'Could not copy automatically. Use your browser copy command instead.';
                status.classList.add('is-error');
            }
        });
    });
});
