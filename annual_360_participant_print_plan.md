# Annual 360 Evaluation: Participant Details and Print Forms

## Goal

Sa HR personnel Evaluation History, magdagdag ng **Participants & Print Forms** tab sa Evaluation Details modal. Makikita rito ang bawat taong may recorded contribution sa Annual 360 evaluation, ang kanilang ratings, comments, at signature, kasama ang sariling print action. Mananatili ang kasalukuyang **Print Form** bilang original consolidated evaluation form.

## Saklaw at access

- Annual 360 evaluations muna ang saklaw. Ipakita lang ang tab kung may Annual evaluation member/cycle record; huwag magpakita ng empty peer forms para sa non-360 o imported historical record.
- Sa HR Manager, HR Supervisor, at HR Staff Evaluation History lang ilalagay ang tab at print links. Sundin ang umiiral na evaluation-history access scope sa bawat role; i-check din ang scope sa bagong print endpoint, hindi lang sa UI.
- Walang participant identity o individual peer form na idaragdag sa Employee Portal. Hindi nito babaguhin ang employee-facing 360 result at peer anonymity.
- Ang print record ay para sa **isang evaluation at isang participant submission**. Hindi dapat makuha ang ibang evaluation sa pagpapalit lang ng URL ID.

## Proposed layout sa Evaluation Details

1. Panatilihin ang kasalukuyang KRA, Behavior, Development Plan, at Comments & Decisions tabs.
2. Magdagdag ng **Participants & Print Forms** tab. Sa taas nito, may **Original consolidated form** entry na may kasalukuyang Print Form link (`manager/print-evaluation.php?id=...`). Maaari ring ilipat dito ang existing Print Form button para hindi madoble ang primary action.
3. Sa ilalim, ipakita ang mga recorded participant bilang compact rows/cards: name, role sa evaluation, submitted/reviewed date, completion state, available rating/comment/signature indicators, **View details**, at **Print this participant**.
4. Sa View details, ipakita ang criterion-level ratings at comments ng piniling participant. Huwag ipakita ang provisional/final combined score bilang personal score ng peer.
5. Kung walang recorded submission, status lang ang ipakita; walang print action at walang blank form na magmumukhang submitted.

## Mga print form at source ng data

| Form | Nilalaman | Kasalukuyang source |
| --- | --- | --- |
| Original consolidated form | Current final KRA, combined Core Values, development plan, comments, signatures, at final result | Existing `manager/print-evaluation.php` |
| Employee self-rating | Sariling KRA at Core Values ratings, self comments, consent, employee signature, submission date | `evaluations`, `evaluation_scores`, `annual_evaluation_members.self_values_json` |
| Department peer, isang form bawat submitted peer | Walong Core Values ratings ng peer para sa target employee, sariling comment kung meron, peer signature, submitted date | `annual_peer_submissions.scores_json`, `rater_signature_data`, `rater_signed_at`, `submitted_at` |
| Immediate-head review | Reviewed/adjusted KRA ratings, review remarks/comments, head signature, approval date; sariling 360 peer rating kung nag-submit din siya | `annual_kra_approvals.ratings_json`, `remarks`, `rater_signature_data`, `rater_signed_at`; `evaluations.supervisor_comments`; matching `annual_peer_submissions` |
| Final decision details | Final score/adjustments, approver, remarks, version/date | `annual_evaluation_versions.snapshot_json`, `remarks`; `evaluations` approval fields |

Ang final decision details ay maaaring nasa original consolidated form at participant tab bilang read-only detail. Hindi gagawa ng hiwalay na signed form kung walang recorded approver signature.

## Comments at signatures: data gap

- Ang Annual peer submission ay may `scores_json` at optional `rater_signature_data`, pero **walang peer comment field** sa kasalukuyang schema at submit function. Magdagdag ng optional `rater_comments` column at comment textarea sa peer rating submission, kasama ang haba/format validation. Isama ang comment sa participant detail at print form.
- Sa old peer submissions, ilagay ang **No comment recorded** kung walang comment; huwag mag-generate ng comment mula sa ibang tao o sa final remarks.
- Sa lumang submission na walang signature, ilagay ang **No signature recorded**. Huwag gumamit ng signature ng ibang participant bilang fallback.
- Kung ang immediate head ay peer rater din, ipakita nang malinaw ang dalawang contribution (peer Core Values at head KRA review) at iwasan ang duplicate person card kung pareho ang employee identity.
- Panatilihing original data ang per-participant ratings/signatures kahit nagbago ang consolidated score sa later approval/version. Ang print header ay magpapakita ng submission/review date at record status.

## Implementation sequence

1. Gumawa ng shared, read-only participant data loader para sa Annual member, self-rating, peer submissions, KRA approval, at final version. I-map ang walong Core Values sa standard order; i-validate ang decoded JSON at huwag mag-assume ng kumpletong data.
2. Magdagdag ng `rater_comments` sa Annual peer submission schema at sa submit handler/UI. Panatilihing nullable para sa existing records.
3. Idagdag ang Participants & Print Forms tab sa HR Evaluation History modal(s). I-reuse ang shared data loader at existing modal tab style. Siguraduhing maayos ang mobile layout at keyboard navigation.
4. Gumawa ng participant print endpoint na tumatanggap ng `evaluation_id` at eksaktong `submission_id`/review record ID. I-check ang HR role, evaluation scope, at relationship ng participant record sa evaluation bago mag-render. Gamitin ang print stylesheet ng current form kung angkop, pero may malinaw na **Participant Rating Record** heading para hindi mapagkamalan sa final consolidated form.
5. Panatilihin at i-regression check ang kasalukuyang `manager/print-evaluation.php` output at link. Walang pagbabago sa final-score computation.

## Acceptance checks

- Para sa Annual evaluation na may self, 3 submitted peers, at immediate-head review, nakikita ng authorized HR user ang original form at lahat ng recorded participant contributions; bawat print ay may tamang pangalan, ratings, comment state, signature state, at date.
- Hindi naghahalo ang peer score sa self score, head KRA rating, o final consolidated score.
- Ang old peer submission na walang comment/signature ay may tapat na missing-data label; hindi lumalabas ang ibang participant's signature.
- Ang non-HR account, invalid participant ID, at participant ID mula sa ibang evaluation ay hindi makapag-print.
- Gumana pa rin ang existing Print Form nang walang pagbabago sa nilalaman at score.
- I-verify sa disposable database na may representative Annual records at sa browser sa desktop/mobile; PHP lint at read-only SQL checks lang ay hindi sapat para patunayang tama ang rendered forms.

## Code pointers

- HR Manager modal: `includes/manager-history-modal.php`; HR Supervisor modal: `includes/supervisor-history-modal.php`; HR Staff history markup: `staff/evaluation-history.php`.
- Existing print form: `manager/print-evaluation.php`.
- Annual data and submit logic: `includes/annual-evaluations.php`, `includes/individual-evaluation-workflow.php`, `database/zLAST_annual_peer_evaluations.sql`.
