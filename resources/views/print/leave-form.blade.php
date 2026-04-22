<!DOCTYPE html>
<html>
@php
    $e = fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    $safeFloat = fn($value) => is_numeric($value) ? (float) $value : 0.0;
    $fmtDisplayDate = function ($date) {
        if (!$date) return '';
        try {
            return \Carbon\Carbon::parse($date)->format('F j, Y');
        } catch (\Throwable $e) {
            return (string) $date;
        }
    };
    $checkbox = fn(bool $checked) => $checked ? '☑' : '☐';
    $normalizeLeaveTypeKey = function (string $name) {
        $key = strtolower(trim($name));
        $key = preg_replace('/\s+/', ' ', $key);
        $key = str_replace([' / ', ' /', '/ '], '/', $key);
        $aliases = [
            'vacation' => 'vacation leave',
            'vacational' => 'vacation leave',
            'annual' => 'vacation leave',
            'vacational leave' => 'vacation leave',
            'vacation leave' => 'vacation leave',
            'sick' => 'sick leave',
            'sick leave' => 'sick leave',
            'mandatory/force leave' => 'mandatory/forced leave',
            'mandatory force leave' => 'mandatory/forced leave',
            'mandatory/forced leave' => 'mandatory/forced leave',
            'force' => 'mandatory/forced leave',
            'force leave' => 'mandatory/forced leave',
            'forced' => 'mandatory/forced leave',
            'forced leave' => 'mandatory/forced leave',
            'mandatory leave' => 'mandatory/forced leave',
            'mandatory' => 'mandatory/forced leave',
            'maternity' => 'maternity leave',
            'maternity leave' => 'maternity leave',
            'paternity' => 'paternity leave',
            'paternity leave' => 'paternity leave',
            'special privilege' => 'special privilege leave',
            'special privilege leave' => 'special privilege leave',
            'solo parent' => 'solo parent leave',
            'solo parent leave' => 'solo parent leave',
            'study' => 'study leave',
            'study leave' => 'study leave',
            'vawc leave' => '10-day vawc leave',
            '10 day vawc leave' => '10-day vawc leave',
            '10-day vawc leave' => '10-day vawc leave',
            'rehabilitation' => 'rehabilitation privilege',
            'rehabilitation privilege' => 'rehabilitation privilege',
            'special leave benefits for women' => 'special leave benefits for women',
            'women special leave' => 'special leave benefits for women',
            'special emergency leave' => 'special emergency (calamity) leave',
            'special emergency (calamity) leave' => 'special emergency (calamity) leave',
            'calamity leave' => 'special emergency (calamity) leave',
            'adoption' => 'adoption leave',
            'adoption leave' => 'adoption leave',
            'monetization of leave credits' => 'monetization of leave credits',
            'terminal leave' => 'terminal leave',
        ];
        return $aliases[$key] ?? $key;
    };

    $selectedLeaveType = $normalizeLeaveTypeKey((string) $leave->leave_type_name);
    $deduct = $safeFloat($leave->total_days);
    $leaveSubtype = strtolower(trim((string) ($leave->leave_subtype ?? '')));
    $detailsData = is_array($details ?? null) ? $details : [];

    $isVacationBucket = ($selectedLeaveType === 'vacation leave');
    $isForceBucket = ($selectedLeaveType === 'mandatory/forced leave');
    $isSickBucket = ($selectedLeaveType === 'sick leave');

    $vacBalanceAfter = $safeFloat($form?->cert_vacation_balance ?? $leave->snapshot_annual_balance ?? 0);
    $sickBalanceAfter = $safeFloat($form?->cert_sick_balance ?? $leave->snapshot_sick_balance ?? 0);
    $forceBalanceAfter = $safeFloat($leave->snapshot_force_balance ?? 0);

    $vacLess = ($form?->cert_vacation_less_this_application !== null)
        ? $safeFloat($form?->cert_vacation_less_this_application)
        : (($isVacationBucket || $isForceBucket) ? $deduct : 0.0);
    $sickLess = ($form?->cert_sick_less_this_application !== null)
        ? $safeFloat($form?->cert_sick_less_this_application)
        : ($isSickBucket ? $deduct : 0.0);

    $vacTotalEarned = ($form?->cert_vacation_total_earned !== null)
        ? $safeFloat($form?->cert_vacation_total_earned)
        : ($vacBalanceAfter + $vacLess);
    $sickTotalEarned = ($form?->cert_sick_total_earned !== null)
        ? $safeFloat($form?->cert_sick_total_earned)
        : ($sickBalanceAfter + $sickLess);

    $vacBalance = $vacBalanceAfter;
    $sickBalance = $sickBalanceAfter;

    $availableForPay = $isSickBucket ? $sickTotalEarned : ($isForceBucket ? min($vacTotalEarned, $forceBalanceAfter + $deduct) : $vacTotalEarned);
    $daysWithPay = ($form?->approved_for_days_with_pay !== null) ? $safeFloat($form?->approved_for_days_with_pay) : min($deduct, $availableForPay);
    $daysWithoutPay = ($form?->approved_for_days_without_pay !== null) ? $safeFloat($form?->approved_for_days_without_pay) : max(0, $deduct - $daysWithPay);
    $approvedOthers = trim((string) ($form?->approved_for_others ?? ''));

    $lastName = trim((string) ($form?->employee_last_name ?: $employee?->last_name));
    $firstName = trim((string) ($form?->employee_first_name ?: $employee?->first_name));
    $middleName = trim((string) ($form?->employee_middle_name ?: $employee?->middle_name));
    $dateOfFiling = $form?->date_of_filing ?: $leave->filing_date ?: $leave->created_at;
    $position = trim((string) (($form?->position_title ?: '') ?: ($employee?->position ?: '')));
    $salary = ($form?->salary && (float)$form->salary > 0) ? (float)$form->salary : (float)($employee?->salary ?? 0);

    $recommendationStatus = strtolower(trim((string) ($form?->recommendation_status ?? '')));
    $recommendationReason = trim((string) (($form?->recommendation_reason ?? '') ?: ($leave->personnel_comments ?: $leave->department_head_comments ?: $leave->manager_comments ?: '')));
    $isDisapproved = strtolower((string) $leave->status) === 'rejected' || $recommendationStatus === 'for_disapproval';

    $commutationRequested = strtolower(trim((string) (($form?->commutation_requested ?? '') ?: ($leave->commutation ?? ''))));
    $commNotRequested = in_array($commutationRequested, ['', 'not_requested', 'not requested', 'no'], true);
    $commRequested = in_array($commutationRequested, ['requested', 'yes'], true);

    $signatoryAName = trim((string) ($form?->personnel_signatory_name_a ?: ($signatories['certification']->name ?? 'ANN GERALYN T. PELIASS')));
    $signatoryAPosition = trim((string) ($form?->personnel_signatory_position_a ?: ($signatories['certification']->position ?? 'Chief Administrative Officer')));
    $signatoryCName = trim((string) ($form?->personnel_signatory_name_c ?: ($signatories['final_approver']->name ?? 'CARLI')));
    $signatoryCPosition = trim((string) ($form?->personnel_signatory_position_c ?: ($signatories['final_approver']->position ?? 'Assistant Regional Director')));

    $departmentHeadFullName = trim(implode(' ', array_filter([$departmentHeadEmployee?->first_name, $departmentHeadEmployee?->middle_name, $departmentHeadEmployee?->last_name])));
    if ($departmentHeadFullName === '') $departmentHeadFullName = 'Chief of the Division/Section or Unit Head';

    $leaveTypeChecks = [
        'vacation leave' => false,
        'mandatory/forced leave' => false,
        'sick leave' => false,
        'maternity leave' => false,
        'paternity leave' => false,
        'special privilege leave' => false,
        'solo parent leave' => false,
        'study leave' => false,
        '10-day vawc leave' => false,
        'rehabilitation privilege' => false,
        'special leave benefits for women' => false,
        'special emergency (calamity) leave' => false,
        'adoption leave' => false,
        'others' => false,
    ];
    if (array_key_exists($selectedLeaveType, $leaveTypeChecks)) $leaveTypeChecks[$selectedLeaveType] = true; else $leaveTypeChecks['others'] = true;

    $detailChecks = [
        'within_ph' => $leaveSubtype === 'within_ph',
        'abroad' => $leaveSubtype === 'abroad',
        'in_hospital' => $leaveSubtype === 'in_hospital',
        'out_patient' => $leaveSubtype === 'out_patient',
        'women_special' => $selectedLeaveType === 'special leave benefits for women',
        'masters' => $leaveSubtype === 'masters',
        'bar_review' => $leaveSubtype === 'bar_review',
        'monetization' => $selectedLeaveType === 'monetization of leave credits',
        'terminal' => $selectedLeaveType === 'terminal leave',
    ];

    $otherLeaveLabel = !$leaveTypeChecks['others'] ? '' : (string) $leave->leave_type_name;
    $sickIllnessText = $isSickBucket && ($detailChecks['in_hospital'] || $detailChecks['out_patient']) ? trim((string) ($detailsData['illness'] ?? '')) : '';
    $womenIllnessText = $leaveTypeChecks['special leave benefits for women'] ? trim((string) ($detailsData['surgery_details'] ?? $detailsData['illness'] ?? '')) : '';
    $studyOtherPurposeText = $leaveTypeChecks['study leave'] ? trim((string) ($detailsData['other_purpose'] ?? $leave->reason ?? '')) : '';
    $showStudyOthers = $leaveTypeChecks['study leave'] && !$detailChecks['masters'] && !$detailChecks['bar_review'] && $studyOtherPurposeText !== '';

    $certificationAsOf = $form?->certification_as_of ?: $leave->finalized_at ?: $leave->personnel_checked_at ?: $leave->department_head_approved_at ?: $leave->created_at ?: now();
    $lawTitle = trim((string) ($leave->leaveTypeRelation?->law_title ?? ''));
    $lawText = trim((string) ($leave->leaveTypeRelation?->law_text ?? ''));
@endphp
<head>
    <meta charset="UTF-8">
    <title>Application for Leave - {{ trim($firstName . ' ' . $lastName) }}</title>
    <link rel="stylesheet" href="{{ asset('assets/css/print_leave_form.css') }}">
</head>
<body>
<div class="page">

    <table class="top-meta">
        <tr>
            <td class="meta-left">
                <div><strong><em>Civil Service Form No. 6</em></strong></div>
                <div><strong><em>Revised 2020</em></strong></div>
            </td>
            <td class="meta-right"><strong>ANNEX A</strong></td>
        </tr>
    </table>

    <div class="header-shell">
        <div class="header-logos">
            <div class="logo-wrap">
                <img src="{{ asset('pictures/DEPED.jpg') }}" alt="DepEd Seal" class="seal-img">
            </div>
            <div class="logo-wrap">
                <img src="{{ asset('pictures/region4.jpg') }}" alt="Region Seal" class="seal-img">
            </div>
        </div>

        <div class="header-center-block">
            <div class="gov-line">Republic of the Philippines</div>
            <div class="gov-line">Department of Education</div>
            <div class="gov-region">Region IV-A CALABARZON</div>
            <div class="gov-sub">Gate 2 Karangalan Village, Cainta, Rizal</div>
        </div>
    </div>

    <div class="main-title">APPLICATION FOR LEAVE</div>

    <table class="leave-form">
        <colgroup>
            <col style="width:12%"><col style="width:13%"><col style="width:15%"><col style="width:14%"><col style="width:12%"><col style="width:14%"><col style="width:10%"><col style="width:10%">
        </colgroup>

        <tr>
            <td colspan="3" class="cell-label head-cell">1.&nbsp; OFFICE/DEPARTMENT</td>
            <td colspan="5" class="cell-label head-cell">2.&nbsp; NAME:
                <span class="name-guide guide-last">(Last)</span>
                <span class="name-guide guide-first">(First)</span>
                <span class="name-guide guide-middle">(Middle)</span>
            </td>
        </tr>
        <tr>
            <td colspan="3" class="cell-value value-row office-value">DEPED REGION IV-A CALABARZON</td>
            <td colspan="2" class="cell-value value-row">{{ $lastName }}</td>
            <td colspan="2" class="cell-value value-row">{{ $firstName }}</td>
            <td colspan="1" class="cell-value value-row">{{ $middleName }}</td>
        </tr>

        <tr>
            <td colspan="2" class="cell-label head-cell">3.&nbsp; DATE OF FILING</td>
            <td colspan="2" class="field-line centered strong">{{ $fmtDisplayDate($dateOfFiling) }}</td>
            <td colspan="1" class="cell-label head-cell">4.&nbsp; POSITION</td>
            <td colspan="2" class="field-line centered">{{ $position }}</td>
            <td colspan="1" class="salary-inline-cell">
                <span class="salary-inline-label">5.&nbsp; SALARY</span>
                <span class="salary-inline-line">{{ $salary > 0 ? number_format($salary, 2) : '' }}</span>
            </td>
        </tr>

        <tr><td colspan="8" class="section-title section-row">6.&nbsp; DETAILS OF APPLICATION</td></tr>
        <tr>
            <td colspan="4" class="subsection-header head-cell">6.A TYPE OF LEAVE TO BE AVAILED OF</td>
            <td colspan="4" class="subsection-header head-cell">6.B DETAILS OF LEAVE</td>
        </tr>

        <tr>
            <td colspan="4" class="top-align list-cell">
                <table class="inner-list">
                    <tr><td class="box">{!! $checkbox($leaveTypeChecks['vacation leave']) !!}</td><td><strong>Vacation Leave</strong> <span class="small-note">(Sec. 51, Rule XVI, Omnibus Rules Implementing E.O. No. 292)</span></td></tr>
                    <tr><td class="box">{!! $checkbox($leaveTypeChecks['mandatory/forced leave']) !!}</td><td><strong>Mandatory/Forced Leave</strong> <span class="small-note">(Sec. 25, Rule XVI, Omnibus Rules Implementing E.O. No. 292)</span></td></tr>
                    <tr><td class="box">{!! $checkbox($leaveTypeChecks['sick leave']) !!}</td><td><strong>Sick Leave</strong> <span class="small-note">(Sec. 43, Rule XVI, Omnibus Rules Implementing E.O. No. 292)</span></td></tr>
                    <tr><td class="box">{!! $checkbox($leaveTypeChecks['maternity leave']) !!}</td><td><strong>Maternity Leave</strong> <span class="small-note">(R.A. No. 11210 / IRR issued by CSC, DOLE and SSS)</span></td></tr>
                    <tr><td class="box">{!! $checkbox($leaveTypeChecks['paternity leave']) !!}</td><td><strong>Paternity Leave</strong> <span class="small-note">(R.A. No. 8187 / CSC MC No. 71, s. 1998, as amended)</span></td></tr>
                    <tr><td class="box">{!! $checkbox($leaveTypeChecks['special privilege leave']) !!}</td><td><strong>Special Privilege Leave</strong> <span class="small-note">(Sec. 21, Rule XVI, Omnibus Rules Implementing E.O. No. 292)</span></td></tr>
                    <tr><td class="box">{!! $checkbox($leaveTypeChecks['solo parent leave']) !!}</td><td><strong>Solo Parent Leave</strong> <span class="small-note">(RA No. 8972 / CSC MC No. 8, s. 2004)</span></td></tr>
                    <tr><td class="box">{!! $checkbox($leaveTypeChecks['study leave']) !!}</td><td><strong>Study Leave</strong> <span class="small-note">(Sec. 68, Rule XVI, Omnibus Rules Implementing E.O. No. 292)</span></td></tr>
                    <tr><td class="box">{!! $checkbox($leaveTypeChecks['10-day vawc leave']) !!}</td><td><strong>10-Day VAWC Leave</strong> <span class="small-note">(RA No. 9262 / CSC MC No. 15, s. 2005)</span></td></tr>
                    <tr><td class="box">{!! $checkbox($leaveTypeChecks['rehabilitation privilege']) !!}</td><td><strong>Rehabilitation Privilege</strong> <span class="small-note">(Sec. 55, Rule XVI, Omnibus Rules Implementing E.O. No. 292)</span></td></tr>
                    <tr><td class="box">{!! $checkbox($leaveTypeChecks['special leave benefits for women']) !!}</td><td><strong>Special Leave Benefits for Women</strong> <span class="small-note">(RA No. 9710 / CSC MC No. 25, s. 2010)</span></td></tr>
                    <tr><td class="box">{!! $checkbox($leaveTypeChecks['special emergency (calamity) leave']) !!}</td><td><strong>Special Emergency (Calamity) Leave</strong> <span class="small-note">(CSC MC No. 2, s. 2012, as amended)</span></td></tr>
                    <tr><td class="box">{!! $checkbox($leaveTypeChecks['adoption leave']) !!}</td><td><strong>Adoption Leave</strong> <span class="small-note">(R.A. No. 8552)</span></td></tr>
                    <tr><td class="box">{!! $checkbox($leaveTypeChecks['others']) !!}</td><td><em>Others:</em> <span class="line-fill">{{ $otherLeaveLabel }}</span></td></tr>
                </table>
            </td>

            <td colspan="4" class="top-align list-cell">
                <table class="inner-list details-list">
                    <tr><td colspan="2" class="italic-head">In case of Vacation/Special Privilege Leave:</td></tr>
                    <tr><td class="box">{!! $checkbox($detailChecks['within_ph']) !!}</td><td>Within the Philippines <span class="inline-line">{{ $detailChecks['within_ph'] ? ($detailsData['location'] ?? '') : '' }}</span></td></tr>
                    <tr><td class="box">{!! $checkbox($detailChecks['abroad']) !!}</td><td>Abroad (Specify) <span class="inline-line">{{ $detailChecks['abroad'] ? ($detailsData['location'] ?? '') : '' }}</span></td></tr>
                    <tr><td colspan="2" class="italic-head">In case of Sick Leave:</td></tr>
                    <tr><td class="box">{!! $checkbox($detailChecks['in_hospital']) !!}</td><td>In Hospital (Specify Illness) <span class="inline-line">{{ $detailChecks['in_hospital'] ? $sickIllnessText : '' }}</span></td></tr>
                    <tr><td class="box">{!! $checkbox($detailChecks['out_patient']) !!}</td><td>Out Patient (Specify Illness) <span class="inline-line">{{ $detailChecks['out_patient'] ? $sickIllnessText : '' }}</span></td></tr>
                    <tr><td colspan="2" class="italic-head">In case of Special Leave Benefits for Women:</td></tr>
                    <tr><td class="box"></td><td>(Specify Illness) <span class="inline-line">{{ $detailChecks['women_special'] ? $womenIllnessText : '' }}</span></td></tr>
                    <tr><td colspan="2" class="italic-head">In case of Study Leave:</td></tr>
                    <tr><td class="box">{!! $checkbox($detailChecks['masters']) !!}</td><td>Completion of Master's Degree</td></tr>
                    <tr><td class="box">{!! $checkbox($detailChecks['bar_review']) !!}</td><td>BAR/Board Examination Review</td></tr>
                    <tr><td class="box">{!! $checkbox($detailChecks['monetization']) !!}</td><td>Monetization of Leave Credits</td></tr>
                    <tr><td class="box">{!! $checkbox($detailChecks['terminal']) !!}</td><td>Terminal Leave</td></tr>
                    <tr><td class="box"></td><td><em>Others:</em> <span class="inline-line">{{ $showStudyOthers ? $studyOtherPurposeText : '' }}</span></td></tr>
                </table>
            </td>
        </tr>

        <tr>
            <td colspan="4" class="subsection-header head-cell no-bottom-cell">6.C NUMBER OF WORKING DAYS APPLIED FOR</td>
            <td colspan="4" class="subsection-header head-cell no-bottom-cell">6.D COMMUTATION</td>
        </tr>
        <tr>
            <td colspan="4" class="days-block top-align no-top-row no-bottom-row">
                <div class="days-line-wrap"><div class="line-wide line-top-value"><span class="days-line-value">{{ number_format($deduct, 3) }}</span></div></div>
                <div class="inclusive-label">INCLUSIVE DATES</div>
                <div class="line-wide centered-date">{{ $fmtDisplayDate($leave->start_date) }}{{ ($leave->start_date || $leave->end_date) ? ' to ' : '' }}{{ $fmtDisplayDate($leave->end_date) }}</div>
            </td>
            <td colspan="4" class="commutation-block top-align no-top-row no-bottom-row">
                <div class="comm-row">{!! $checkbox($commNotRequested) !!} <span>Not Requested</span></div>
                <div class="comm-row">{!! $checkbox($commRequested) !!} <span>Requested</span></div>
                <div class="applicant-signature">(Signature of Applicant)</div>
            </td>
        </tr>

        <tr><td colspan="8" class="section-title section-row">7.&nbsp; DETAILS OF ACTION ON APPLICATION</td></tr>
        <tr>
            <td colspan="4" class="subsection-header head-cell">7.A CERTIFICATION OF LEAVE CREDITS</td>
            <td colspan="4" class="subsection-header head-cell">7.B RECOMMENDATION</td>
        </tr>
        <tr>
            <td colspan="4" class="top-align cert-cell">
                <div class="as-of-wrap">As of <span class="as-of-line">{{ $fmtDisplayDate($certificationAsOf) }}</span></div>
                <table class="credits-table">
                    <tr><th></th><th>Vacation Leave</th><th>Sick Leave</th></tr>
                    <tr><td><em>Total Earned</em></td><td>{{ number_format($vacTotalEarned, 3) }}</td><td>{{ number_format($sickTotalEarned, 3) }}</td></tr>
                    <tr><td><em>Less this application</em></td><td>{{ number_format($vacLess, 3) }}</td><td>{{ number_format($sickLess, 3) }}</td></tr>
                    <tr><td><em>Balance</em></td><td>{{ number_format($vacBalance, 3) }}</td><td>{{ number_format($sickBalance, 3) }}</td></tr>
                </table>
                <div class="sig-area cert-sign">
                    <div class="sig-name">{{ $signatoryAName }}</div>
                    <div class="sig-line"></div>
                    <div class="sig-pos">{{ $signatoryAPosition }}</div>
                </div>
            </td>
            <td colspan="4" class="top-align recommendation-cell">
                <div class="rec-row">{!! $checkbox(!$isDisapproved) !!} <span>For approval</span></div>
                <div class="rec-row">{!! $checkbox($isDisapproved) !!} <span>For disapproval due to</span> <span class="reason-line short-reason">{{ $isDisapproved ? $recommendationReason : '' }}</span></div>
                <div class="reason-line big"></div>
                <div class="reason-line big"></div>
                <div class="reason-line big"></div>
                <div class="sig-area lower">
                    <div class="sig-name dept-head-sign">{{ $departmentHeadFullName }}</div>
                    <div class="sig-line"></div>
                    <div class="sig-pos">Chief of the Division/Section or Unit Head</div>
                </div>
            </td>
        </tr>
        <tr>
            <td colspan="4" class="top-align approval-cell no-right-border">
                <div class="approve-title">7.C APPROVED FOR:</div>
                <div class="approve-row"><span class="short-line">{{ number_format($daysWithPay, 3) }}</span> day with pay</div>
                <div class="approve-row"><span class="short-line">{{ number_format($daysWithoutPay, 3) }}</span> days without pay</div>
                <div class="approve-row"><span class="short-line">{{ $approvedOthers }}</span> others (Specify)</div>
            </td>
            <td colspan="4" class="top-align disapprove-cell no-left-border">
                <div class="approve-title">7.&nbsp;D&nbsp; DISAPPROVED DUE TO:</div>
                <div class="reason-line big"></div>
                <div class="reason-line big"></div>
                <div class="reason-line big"></div>
            </td>
        </tr>
        <tr>
            <td colspan="8" class="final-signatory-row">
                <div class="sig-area centered-final">
                    <div class="sig-name final">{{ $signatoryCName }}</div>
                    <div class="sig-line"></div>
                    <div class="sig-pos">{{ $signatoryCPosition }}</div>
                </div>
            </td>
        </tr>
    </table>

    @if($lawTitle !== '' || $lawText !== '')
        <div class="law-note">
            <strong>Related Law:</strong> {{ $lawTitle }}
            @if($lawText !== '')
                <div class="law-text">{!! nl2br(e($lawText)) !!}</div>
            @endif
        </div>
    @endif
</div>
<script>window.print();</script>
</body>
</html>
