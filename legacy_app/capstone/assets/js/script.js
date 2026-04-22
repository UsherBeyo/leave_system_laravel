
window.AppLoader = window.AppLoader || {
    show: function() {},
    hide: function() {}
};

function calculateDays(onComplete) {
    const startField = document.getElementById("start_date");
    const endField = document.getElementById("end_date");
    const totalField = document.getElementById("total_days");
    const feedbackField = document.getElementById("date-range-feedback");

    if (!startField || !endField || !totalField) {
        if (typeof onComplete === 'function') onComplete();
        return;
    }

    const start = startField.value;
    const end = endField.value;

    const setFeedback = (message, isError = false) => {
        if (!feedbackField) return;
        feedbackField.textContent = message || '';
        feedbackField.style.color = isError ? '#b91c1c' : '#6b7280';
    };

    if (!start || !end) {
        totalField.value = '';
        setFeedback('Select both start and end dates to compute deductible working days.');
        if (typeof onComplete === 'function') onComplete();
        return;
    }

    if (end < start) {
        totalField.value = '0';
        setFeedback('End date cannot be earlier than start date.', true);
        if (typeof window.checkBalanceWarning === 'function') {
            window.checkBalanceWarning(0);
        }
        if (typeof onComplete === 'function') onComplete();
        return;
    }

    fetch(`../api/calc_days.php?start=${encodeURIComponent(start)}&end=${encodeURIComponent(end)}`)
        .then(res => res.json())
        .then(data => {
            const days = Number.isFinite(Number(data.days)) ? Number(data.days) : 0;
            totalField.value = String(days);

            if (data.valid === false) {
                setFeedback(data.message || 'Please enter a valid date range.', true);
            } else if (days <= 0) {
                setFeedback(data.message || 'The selected range contains no deductible working days.', true);
            } else {
                const holidayDays = Number(data.holiday_days || 0);
                const weekendDays = Number(data.weekend_days || 0);
                let summary = `${days} working day(s)`;
                const exclusions = [];
                if (weekendDays > 0) exclusions.push(`${weekendDays} weekend day(s)`);
                if (holidayDays > 0) exclusions.push(`${holidayDays} holiday(s)`);
                if (exclusions.length > 0) {
                    summary += ` after excluding ${exclusions.join(' and ')}`;
                }
                setFeedback(summary);
            }

            if (typeof window.checkBalanceWarning === 'function') {
                window.checkBalanceWarning(days);
            }
        })
        .catch(() => {
            totalField.value = '';
            setFeedback('Unable to calculate deductible days right now. Please try again.', true);
            if (typeof window.checkBalanceWarning === 'function') {
                window.checkBalanceWarning(0);
            }
        })
        .finally(() => {
            if (typeof onComplete === 'function') onComplete();
        });
}

// safe form submit handler (only when a form and password field exist)
var _form = document.querySelector('form');
if (_form) {
    _form.addEventListener('submit', function(e){
        var pwdField = document.querySelector('input[name="password"]');
        if (pwdField) {
            var pwd = pwdField.value || '';
            if (pwd.length > 0 && pwd.length < 6) {
                alert("Password must be at least 6 characters.");
                e.preventDefault();
            }
        }
    });
}

// toggle shadow removal on scroll to reduce heavy background shadow when scrolled
window.addEventListener('scroll', function() {
    if (window.scrollY > 20) document.body.classList.add('no-shadow');
    else document.body.classList.remove('no-shadow');
});

function initCollapsibleSections() {
    document.querySelectorAll('.collapsible-card').forEach((card) => {
        var header = card.querySelector('.collapsible-header');
        var body = card.querySelector('.collapsible-body');
        var toggle = card.querySelector('.collapsible-toggle');
        if (!header || !body || !toggle) return;

        var setExpanded = function(expanded) {
            body.classList.toggle('expanded', expanded);
            toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            toggle.textContent = expanded ? '▾' : '▸';
        };

        setExpanded(true);

        header.addEventListener('click', function() {
            var expanded = body.classList.contains('expanded');
            setExpanded(!expanded);
        });

        toggle.addEventListener('click', function(e) {
            e.stopPropagation();
            var expanded = body.classList.contains('expanded');
            setExpanded(!expanded);
        });
    });
}

document.addEventListener('DOMContentLoaded', initCollapsibleSections);

function initAjaxFragments(root) {
    var scope = root || document;
    var fragments = scope.querySelectorAll('.ajax-fragment[data-fragment-id]');
    fragments.forEach(function(fragment) {
        if (fragment.dataset.ajaxBound === '1') return;
        fragment.dataset.ajaxBound = '1';

        var fragmentId = fragment.dataset.fragmentId;
        var pageParam = fragment.dataset.pageParam || 'page';
        var searchParam = fragment.dataset.searchParam || 'q';
        var searchInput = fragment.querySelector('.live-search-input');
        var debounceTimer = null;
        var requestToken = 0;

        var setLoading = function(isLoading) {
            fragment.classList.toggle('is-loading', !!isLoading);
        };

        var replaceFragmentFromUrl = function(urlString) {
            requestToken += 1;
            var currentToken = requestToken;
            var activeElement = document.activeElement;
            var shouldRestoreFocus = !!(activeElement && fragment.contains(activeElement) && activeElement.classList && activeElement.classList.contains('live-search-input'));
            var activeSelectionStart = shouldRestoreFocus && typeof activeElement.selectionStart === 'number' ? activeElement.selectionStart : null;
            var activeSelectionEnd = shouldRestoreFocus && typeof activeElement.selectionEnd === 'number' ? activeElement.selectionEnd : null;
            setLoading(true);
            if (window.AppLoader) window.AppLoader.show();

            fetch(urlString, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            })
                .then(function(response) {
                    return response.text();
                })
                .then(function(html) {
                    if (currentToken !== requestToken) return;
                    var parser = new DOMParser();
                    var doc = parser.parseFromString(html, 'text/html');
                    var selector = '.ajax-fragment[data-fragment-id="' + fragmentId + '"]';
                    var nextFragment = doc.querySelector(selector);
                    if (!nextFragment) return;

                    fragment.replaceWith(nextFragment);
                    if (window.history && window.history.replaceState) {
                        window.history.replaceState({}, '', urlString);
                    }
                    initAjaxFragments(document);

                    if (shouldRestoreFocus) {
                        var currentFragment = document.querySelector(selector);
                        var nextSearchInput = currentFragment ? currentFragment.querySelector('.live-search-input') : null;
                        if (nextSearchInput) {
                            nextSearchInput.focus({ preventScroll: true });
                            if (typeof nextSearchInput.setSelectionRange === 'function' && activeSelectionStart !== null && activeSelectionEnd !== null) {
                                var maxLength = nextSearchInput.value.length;
                                nextSearchInput.setSelectionRange(Math.min(activeSelectionStart, maxLength), Math.min(activeSelectionEnd, maxLength));
                            }
                        }
                    }
                })
                .catch(function(error) {
                    console.error('Live fragment update failed:', error);
                })
                .finally(function() {
                    var currentFragment = document.querySelector('.ajax-fragment[data-fragment-id="' + fragmentId + '"]');
                    if (currentFragment) {
                        currentFragment.classList.remove('is-loading');
                        if (window.AppLoader) window.AppLoader.hide();
                    }
                });
        };

        fragment.addEventListener('click', function(event) {
            var link = event.target.closest('.pagination-link[href]');
            if (!link || !fragment.contains(link)) return;
            event.preventDefault();
            var nextUrl = new URL(link.href, window.location.href);
            var activeTab = document.querySelector('.filter-tab.is-active[data-tab]');
            if (activeTab && /\/leave_requests\.php$/i.test(window.location.pathname)) {
                nextUrl.searchParams.set('tab', activeTab.getAttribute('data-tab'));
            }
            replaceFragmentFromUrl(nextUrl.toString());
        });

        if (searchInput) {
            searchInput.addEventListener('input', function() {
                var url = new URL(window.location.href);
                var value = searchInput.value || '';
                if (value.trim() === '') {
                    url.searchParams.delete(searchParam);
                } else {
                    url.searchParams.set(searchParam, value);
                }
                url.searchParams.set(pageParam, '1');

                clearTimeout(debounceTimer);
                debounceTimer = window.setTimeout(function() {
                    replaceFragmentFromUrl(url.toString());
                }, 180);
            });
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    initAjaxFragments(document);
});
