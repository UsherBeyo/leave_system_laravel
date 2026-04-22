Changed file:
- app/Services/LeaveWorkflowService.php

What was wrong:
- LeaveRequestController was calling LeaveWorkflowService::previewApprovalImpact(), but that method did not exist in the current uploaded repository.
- The same service file was also missing several related workflow methods and helpers that the current controllers rely on.

What was changed:
- Added public method previewApprovalImpact() back.
- Restored public workflow methods used by the current controllers:
  - reject()
  - returnToDepartmentHead()
  - markPrinted()
- Restored private helpers required by apply/final approval flow:
  - guardBalance()
  - resolveDepartmentHeadUserId()
  - isLateSickFiling()
  - logBudgetChange()
  - upsertLeaveRequestFormApprovalData()
  - computeDeductionProfile()
- Kept attachment saving fix in place:
  - reads size/mime before move
  - creates upload folder if missing

What was not changed:
- routes
- views
- DB schema
- print layout
- request row layout
- pagination layout
