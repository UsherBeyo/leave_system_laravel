@extends('layouts.app')
@section('title', 'Login - Leave System')
@section('body_class', 'login-page')
@section('content')
<div class="login-scene" aria-hidden="true">
    <span class="login-orb login-orb-1"></span>
    <span class="login-orb login-orb-2"></span>
    <span class="login-orb login-orb-3"></span>
    <span class="login-grid"></span>
</div>

<div class="login-shell login-shell--enhanced">
    <section class="login-brand-panel login-brand-panel--enhanced">
        <div class="login-logo-stack" aria-label="Official logos">
            <div class="login-logo-chip">
                <img src="{{ asset('pictures/DEPED-removebg-preview.png') }}" alt="DepEd Logo" class="login-logo login-logo--deped">
            </div>
            <div class="login-logo-divider"></div>
            <div class="login-logo-chip">
                <img src="{{ asset('pictures/region4-removebg-preview.png') }}" alt="Region 4 Logo" class="login-logo login-logo--region">
            </div>
        </div>

        <div class="login-brand-copy-minimal">
            <div class="login-brand-badge">DepEd Region IV-A</div>
            <h1 class="login-brand-title">Leave Management System</h1>
        </div>

        <div class="login-showcase-card login-showcase-card--minimal">
            <div class="login-mascot-wrap">
                <div class="login-mascot" id="loginMascot" aria-hidden="true">
                    <div class="login-mascot-antenna"></div>
                    <div class="login-mascot-head">
                        <span class="login-mascot-eye"><span class="login-mascot-pupil" data-eye="left"></span></span>
                        <span class="login-mascot-eye"><span class="login-mascot-pupil" data-eye="right"></span></span>
                    </div>
                    <div class="login-mascot-mouth"></div>
                </div>
                <div class="login-mascot-rings"></div>
            </div>
        </div>
    </section>

    <section class="ui-card login-card login-card--enhanced">
        <div class="login-card-head login-card-head--enhanced">
            <div class="login-card-kicker">Secure access</div>
            <h2>Sign in</h2>
        </div>

        @if ($errors->any())
            <div class="login-inline-alert error">
                <div class="login-inline-alert-icon">!</div>
                <div>
                    <strong>Login failed</strong>
                    <span>{{ $errors->first() }}</span>
                </div>
            </div>
        @endif

        <form action="{{ route('login.perform') }}" method="POST" class="login-form login-form--enhanced">
            @csrf

            <div class="login-field">
                <label for="loginEmail">Email</label>
                <div class="login-input-wrap">
                    <span class="login-input-icon" aria-hidden="true">@</span>
                    <input id="loginEmail" type="email" name="email" required class="login-input" placeholder="Enter your email" value="{{ old('email') }}">
                </div>
            </div>

            <div class="login-field">
                <label for="loginPassword">Password</label>
                <div class="login-input-wrap">
                    <span class="login-input-icon" aria-hidden="true">*</span>
                    <input id="loginPassword" type="password" name="password" required class="login-input login-input--with-toggle" placeholder="Enter your password">
                    <button type="button" class="login-password-toggle" id="togglePassword" aria-label="Show password">Show</button>
                </div>
            </div>

            <div class="login-privacy-row">
                <input type="checkbox" id="agreePrivacy" name="agree_privacy" value="1" required>
                <label for="agreePrivacy">I agree to the <a href="#" onclick="openPrivacyModal(event)">Data Privacy and Terms</a></label>
            </div>

            <button type="submit" class="login-submit-btn">Login</button>
        </form>
    </section>
</div>

<div id="privacyModal" class="modal login-privacy-modal" style="display:none;">
    <div class="modal-content login-privacy-content">
        <span class="modal-close" id="closePrivacyModal">&times;</span>
        <h3>Data Privacy & Terms of Service</h3>
        <div class="login-privacy-scroll">
            <h4>1. Data Privacy Notice</h4>
            <p>We collect and process personal information including your name, email, and employment details. This information is used solely for leave management and HR administration purposes.</p>
            <h4>2. Data Protection</h4>
            <p>Your data is protected with industry-standard security measures. We do not share your personal information with third parties without your consent, except as required by law.</p>
            <h4>3. Use of Information</h4>
            <p>Leave records, including dates and reasons, are maintained for business and regulatory compliance purposes. Leave balances and history are accessible to authorized HR and management personnel only.</p>
            <h4>4. Retention</h4>
            <p>Employment and leave records are retained for the duration of your employment and for a period thereafter as required by applicable laws.</p>
            <h4>5. Your Rights</h4>
            <p>You have the right to access, correct, or request deletion of your personal data, subject to legal and contractual obligations.</p>
            <h4>6. Terms of Use</h4>
            <p>By logging in, you agree to use this system in accordance with company policies and applicable laws. Unauthorized access, data tampering, or misuse is prohibited.</p>
            <h4>7. Disclaimer</h4>
            <p>The leave management system is provided on an as-is basis. We are not liable for any data loss or system downtime beyond our control.</p>
            <h4>8. Changes to Policy</h4>
            <p>We reserve the right to update this policy. Continued use of the system constitutes acceptance of any changes.</p>
        </div>
        <div class="login-privacy-actions">
            <button type="button" id="closePrivacyBtn" class="btn-primary">Close</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function openPrivacyModal(e) {
    e.preventDefault();
    document.getElementById('privacyModal').style.display = 'flex';
}

function closePrivacyModal() {
    document.getElementById('privacyModal').style.display = 'none';
}

document.addEventListener('DOMContentLoaded', function() {
    var closeX = document.getElementById('closePrivacyModal');
    var closeBtn = document.getElementById('closePrivacyBtn');
    var toggleBtn = document.getElementById('togglePassword');
    var passwordInput = document.getElementById('loginPassword');
    var pupils = document.querySelectorAll('.login-mascot-pupil');
    var shell = document.querySelector('.login-shell--enhanced');
    var active = false;
    var targetX = 0;
    var targetY = 0;
    var raf = null;

    if (closeX) closeX.addEventListener('click', closePrivacyModal);
    if (closeBtn) closeBtn.addEventListener('click', closePrivacyModal);

    window.addEventListener('click', function(e) {
        var modal = document.getElementById('privacyModal');
        if (e.target === modal) closePrivacyModal();
    });

    if (toggleBtn && passwordInput) {
        toggleBtn.addEventListener('click', function() {
            var isPassword = passwordInput.getAttribute('type') === 'password';
            passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
            toggleBtn.textContent = isPassword ? 'Hide' : 'Show';
        });
    }

    function updateMascot() {
        if (!active) { raf = null; return; }
        pupils.forEach(function(pupil) {
            pupil.style.transform = 'translate(' + targetX + 'px, ' + targetY + 'px)';
        });
        raf = requestAnimationFrame(updateMascot);
    }

    document.addEventListener('mousemove', function(event) {
        var mascot = document.getElementById('loginMascot');
        if (!mascot) return;
        var rect = mascot.getBoundingClientRect();
        var centerX = rect.left + rect.width / 2;
        var centerY = rect.top + rect.height / 2;
        var dx = (event.clientX - centerX) / 30;
        var dy = (event.clientY - centerY) / 30;
        targetX = Math.max(-4, Math.min(4, dx));
        targetY = Math.max(-4, Math.min(4, dy));
        active = true;
        if (!raf) raf = requestAnimationFrame(updateMascot);
        if (shell) {
            var x = (event.clientX / window.innerWidth) * 100;
            var y = (event.clientY / window.innerHeight) * 100;
            shell.style.setProperty('--cursor-x', x + '%');
            shell.style.setProperty('--cursor-y', y + '%');
        }
    });
});
</script>
@endpush
