/**
 * Copyright © Magefan (support@magefan.com). All rights reserved.
 * Please visit Magefan.com for license details (https://magefan.com/end-user-license-agreement).
 */
define(['jquery', 'mage/backend/validation'], function ($) {
    'use strict';

    const POLL_INTERVAL = 2000;
    const MAX_POLL_INTERVAL = 30000;
    const RETRY_DELAYS = [2000, 4000, 8000];
    const FINAL_STATUSES = ['done', 'failed', 'cancelled'];

    /**
     * "Shopify default blog" destination of the export form.
     *
     * The other destination keeps the form's own submit to the run page.
     */
    return function (options, root) {
        const config = JSON.parse(root.getAttribute('data-mfsbe-bi-config'));
        const i18n = config.i18n;

        const find = function (name) {
            return root.querySelector('[data-mfsbe-bi-' + name + ']');
        };

        const el = {
            form: document.getElementById('edit_form'),
            destination: document.getElementById('export_destination'),
            key: document.getElementById('export_shopify_import_key'),
            keyNote: document.getElementById('export_shopify_import_key_note'),
            submit: document.getElementById('save'),
            formError: find('form-error'),
            shop: find('shop'),
            progress: find('progress'),
            progressTitle: find('progress-title'),
            bar: find('bar'),
            barFill: find('bar-fill'),
            count: find('count'),
            note: find('note'),
            failed: find('failed'),
            failedList: find('failed-list'),
            failedMore: find('failed-more'),
            resume: find('resume'),
            newExport: find('new'),
            cancel: find('cancel'),
            error: find('error')
        };

        if (!el.form || !el.destination || !el.key || !el.submit) {
            return;
        }

        const keyNote = el.keyNote ? el.keyNote.textContent : '';
        let busy = false;
        let pollTimer = null;
        let pollDelay = POLL_INTERVAL;
        let conflictJobId = 0;

        const format = function (template) {
            const values = Array.prototype.slice.call(arguments, 1);
            return template.replace(/%(\d+)/g, function (match, index) {
                return String(values[index - 1]);
            });
        };

        const show = function (element, visible) {
            element.hidden = !visible;
        };

        /**
         * Show or hide the form and its button, whose admin styles override the hidden attribute.
         */
        const showFormElement = function (element, visible) {
            element.style.display = visible ? '' : 'none';
        };

        const wait = function (ms) {
            return new Promise(function (resolve) {
                setTimeout(resolve, ms);
            });
        };

        const isSelected = function () {
            return config.destination === el.destination.value;
        };

        const request = function (action, data) {
            const body = new FormData();
            body.append('form_key', config.formKey);
            Object.keys(data || {}).forEach(function (name) {
                body.append(name, data[name]);
            });

            return fetch(config.urls[action], {
                method: 'POST',
                credentials: 'same-origin',
                headers: {'X-Requested-With': 'XMLHttpRequest'},
                body: body
            })
                .catch(function () {
                    throw {message: i18n.genericError, retryable: true};
                })
                .then(function (response) {
                    return response.json()
                        .catch(function () {
                            throw {message: i18n.genericError, retryable: 500 <= response.status};
                        })
                        .then(function (json) {
                            if (json.ajaxExpired) {
                                window.location.reload();
                                throw {message: i18n.genericError, retryable: false};
                            }
                            if (!response.ok) {
                                throw {
                                    message: json.message || i18n.genericError,
                                    retryable: !!json.retryable,
                                    jobId: json.job_id || 0
                                };
                            }
                            return json;
                        });
                });
        };

        const requestWithRetry = function (action, data) {
            let attempt = 0;

            const run = function () {
                return request(action, data).catch(function (error) {
                    if (!error.retryable || attempt >= RETRY_DELAYS.length) {
                        throw error;
                    }
                    showNote(i18n.retrying);
                    return wait(RETRY_DELAYS[attempt++]).then(run);
                });
            };

            return run().then(function (result) {
                show(el.note, false);
                return result;
            });
        };

        const showFormError = function (message) {
            el.formError.textContent = message;
            show(el.formError, !!message);
        };

        const showError = function (message) {
            el.error.textContent = message;
            show(el.error, !!message);
        };

        const showNote = function (message) {
            el.note.textContent = message;
            show(el.note, !!message);
        };

        const setBar = function (done, total, state) {
            const percent = total ? Math.min(100, Math.round(done / total * 100)) : 100;
            el.barFill.style.width = percent + '%';
            el.bar.setAttribute('aria-valuenow', String(percent));
            el.bar.classList.toggle('mfsbe-bi-bar--waiting', 'waiting' === state);
            el.bar.classList.toggle('mfsbe-bi-bar--done', 'done' === state);
        };

        const setActions = function (actions) {
            show(el.resume, -1 !== actions.indexOf('resume'));
            show(el.newExport, -1 !== actions.indexOf('new'));
            show(el.cancel, -1 !== actions.indexOf('cancel'));
        };

        const setSubmit = function (label) {
            busy = !!label;
            el.submit.disabled = busy || (isSelected() && !(config.totals && config.totals.posts));
            if (label) {
                el.submit.setAttribute('data-mfsbe-bi-label', el.submit.textContent);
                el.submit.querySelector('span').textContent = label;
            } else if (el.submit.hasAttribute('data-mfsbe-bi-label')) {
                el.submit.querySelector('span').textContent = el.submit.getAttribute('data-mfsbe-bi-label');
                el.submit.removeAttribute('data-mfsbe-bi-label');
            }
        };

        const stopPolling = function () {
            clearTimeout(pollTimer);
            pollTimer = null;
        };

        /**
         * Key hint for the selected destination.
         */
        const applyDestination = function () {
            showFormError('');
            if (el.keyNote) {
                if (!isSelected()) {
                    el.keyNote.textContent = keyNote;
                } else if (!config.totals) {
                    el.keyNote.textContent = i18n.notInstalled;
                } else {
                    el.keyNote.textContent = i18n.keyHint + ' ' + (config.totals.posts
                        ? format(i18n.summary, config.totals.posts, config.totals.blogs)
                        : i18n.nothing);
                }
            }
            setSubmit('');
        };

        const showForm = function () {
            stopPolling();
            showFormElement(el.form, true);
            showFormElement(el.submit, true);
            show(el.progress, false);
            showFormError('');
            conflictJobId = 0;
            setSubmit('');
        };

        const showProgress = function () {
            showFormElement(el.form, false);
            showFormElement(el.submit, false);
            show(el.progress, true);
            show(el.failed, false);
            el.shop.textContent = config.shop ? format(i18n.exportingTo, config.shop) : '';
        };

        const renderSending = function (job) {
            const sendingBlogs = job.blogs_sent < job.blogs_total;
            const done = sendingBlogs ? job.blogs_sent : job.posts_sent;
            const total = sendingBlogs ? job.blogs_total : job.posts_total;
            el.progressTitle.textContent = sendingBlogs ? i18n.sendingBlogs : i18n.sendingPosts;
            el.count.textContent = done + ' / ' + total;
            setBar(done, total, 'sending');
        };

        const isSent = function (job) {
            return job.blogs_sent >= job.blogs_total && job.posts_sent >= job.posts_total;
        };

        const sendAll = function (job) {
            showProgress();
            showError('');
            showNote('');
            setActions(['cancel']);
            renderSending(job);

            if (isSent(job)) {
                return finish();
            }

            return requestWithRetry('send')
                .then(function (updated) {
                    config.job = updated;
                    return sendAll(updated);
                })
                .catch(function (error) {
                    showError(error.message);
                    setActions(['resume', 'cancel']);
                });
        };

        const finish = function () {
            return requestWithRetry('finish')
                .then(function () {
                    config.job.finished = true;
                    showNote(i18n.canClose);
                    pollStatus();
                })
                .catch(function (error) {
                    showError(error.message);
                    setActions(['resume', 'cancel']);
                });
        };

        const renderFailed = function (status) {
            const failed = status.failed || [];
            el.failedList.textContent = '';
            failed.forEach(function (post) {
                const item = document.createElement('li');
                const title = document.createElement('strong');
                title.textContent = post.title || ('#' + post.id);
                item.appendChild(title);
                item.appendChild(document.createTextNode(' — ' + (post.error || '')));
                el.failedList.appendChild(item);
            });
            show(el.failed, 0 < status.posts_failed);
            el.failedMore.textContent = i18n.moreFailed;
            show(el.failedMore, status.posts_failed > failed.length);
        };

        const renderStatus = function (status) {
            const handled = status.posts_done + status.posts_failed;
            el.count.textContent = handled + ' / ' + status.posts_total;
            renderFailed(status);

            if ('queued' === status.status) {
                el.progressTitle.textContent = i18n.waiting;
                setBar(0, 1, 'waiting');
                setActions(['cancel']);
                return;
            }

            if ('processing' === status.status || 'receiving' === status.status) {
                el.progressTitle.textContent = i18n.importing;
                setBar(handled, status.posts_total, 'importing');
                setActions(['cancel']);
                return;
            }

            showNote('');
            setActions(['new']);

            if ('done' === status.status) {
                el.progressTitle.textContent = i18n.done;
                el.count.textContent = format(i18n.doneSummary, status.posts_done, status.posts_failed);
                setBar(1, 1, 'done');
            } else if ('cancelled' === status.status) {
                el.progressTitle.textContent = i18n.cancelled;
                setBar(handled, status.posts_total, 'cancelled');
            } else {
                el.progressTitle.textContent = i18n.failedJob;
                setBar(handled, status.posts_total, 'failed');
                showError(status.error || '');
            }
        };

        const pollStatus = function () {
            stopPolling();
            showProgress();

            if (document.hidden) {
                return;
            }

            request('status')
                .then(function (status) {
                    pollDelay = POLL_INTERVAL;
                    showError('');
                    renderStatus(status);
                    if (-1 === FINAL_STATUSES.indexOf(status.status)) {
                        pollTimer = setTimeout(pollStatus, pollDelay);
                    }
                })
                .catch(function (error) {
                    showError(error.message);
                    pollDelay = Math.min(MAX_POLL_INTERVAL, pollDelay * 2);
                    pollTimer = setTimeout(pollStatus, pollDelay);
                });
        };

        const startExport = function () {
            showFormError('');
            setSubmit(i18n.connecting);

            request('connect', {connection_key: el.key.value})
                .then(function (data) {
                    config.shop = data.shop;
                    config.job = null;
                    setSubmit(i18n.starting);
                    return request('start', {source: config.source});
                })
                .then(function (job) {
                    config.job = job;
                    el.key.value = '';
                    setSubmit('');
                    return sendAll(job);
                })
                .catch(function (error) {
                    setSubmit('');
                    conflictJobId = error.jobId || 0;
                    if (!conflictJobId) {
                        showFormError(error.message);
                        return;
                    }
                    showProgress();
                    showError(i18n.conflict + ' ' + error.message);
                    el.progressTitle.textContent = i18n.conflict;
                    el.count.textContent = '';
                    setBar(0, 1, 'waiting');
                    setActions(['cancel']);
                });
        };

        /**
         * Run the export on this page instead of submitting the form to the run page.
         *
         * @param {Event} event
         */
        const interceptSubmit = function (event) {
            if (!isSelected()) {
                return;
            }

            event.preventDefault();
            event.stopImmediatePropagation();
            if (!busy && $(el.form).valid()) {
                startExport();
            }
        };

        el.destination.addEventListener('change', applyDestination);

        // "Start Export" button: the backend form widget asks beforeSubmit handlers before it submits.
        $(el.form).on('beforeSubmit', interceptSubmit);

        // Enter in the key field submits the form natively; the capture listener runs before validation.
        el.form.addEventListener('submit', interceptSubmit, true);

        el.resume.addEventListener('click', function () {
            sendAll(config.job);
        });

        el.newExport.addEventListener('click', function () {
            el.newExport.disabled = true;

            // The ended export is saved on the server; forget it, or a reloaded page shows it again.
            request('dismiss')
                .then(function () {
                    config.job = null;
                    showForm();
                })
                .catch(function (error) {
                    showError(error.message);
                })
                .finally(function () {
                    el.newExport.disabled = false;
                });
        });

        el.cancel.addEventListener('click', function () {
            if (!window.confirm(i18n.confirmCancel)) {
                return;
            }

            stopPolling();
            el.cancel.disabled = true;

            request('cancel', conflictJobId ? {job_id: conflictJobId} : {})
                .then(function (data) {
                    if (!conflictJobId) {
                        config.job = null;
                    }
                    showForm();
                    if (data && data.warning) {
                        showFormError(data.warning);
                    }
                })
                .catch(function (error) {
                    showError(error.message);
                })
                .finally(function () {
                    el.cancel.disabled = false;
                });
        });

        document.addEventListener('visibilitychange', function () {
            if (!document.hidden && config.job && config.job.finished && null === pollTimer && !el.progress.hidden) {
                pollStatus();
            }
        });

        if (config.job) {
            el.destination.value = config.destination;
            applyDestination();
            if (!config.job.finished) {
                showProgress();
                renderSending(config.job);
                showNote(i18n.interrupted);
                setActions(['resume', 'cancel']);
            } else {
                pollStatus();
            }
        }
    };
});
