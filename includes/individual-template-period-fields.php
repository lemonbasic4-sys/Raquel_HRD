<?php $periodTemplate=$tmpl ?? []; ?>
<div class="row g-3 mb-3" id="individualTemplatePeriod">
    <div class="col-12"><strong>Employee evaluation period</strong><p class="form-text mb-0">Publishing this form makes it available to eligible employees. No department Start Cycle is required. Revision and Effective Date are separate document dates.</p></div>
    <div class="col-md-6" data-individual-type="Annual"><label for="assessmentYear" class="form-label">Annual year</label><input id="assessmentYear" class="form-control" name="assessment_year" type="number" min="1900" max="2200" value="<?php echo e(substr($periodTemplate['assessment_from'] ?? date('Y'),0,4)); ?>"><div class="form-text">January 1 or the employee's regularization date (whichever is later), through December 31.</div></div>
    <div class="col-md-6" data-individual-type="Quarterly"><label for="assessmentFrom" class="form-label">Period From</label><input id="assessmentFrom" class="form-control" name="assessment_from" type="date" value="<?php echo e($periodTemplate['assessment_from'] ?? ''); ?>"></div>
    <div class="col-md-6" data-individual-type="Quarterly"><label for="assessmentTo" class="form-label">Period To</label><input id="assessmentTo" class="form-control" name="assessment_to" type="date" value="<?php echo e($periodTemplate['assessment_to'] ?? ''); ?>"><div class="form-text">Choose the actual period; calendar quarters are not enforced.</div></div>
</div>
<script>
(function(){
 const type=document.getElementById('inputEvalType');
 const box=document.getElementById('individualTemplatePeriod');
 function sync(){
  box.hidden=!['Annual','Quarterly'].includes(type.value);
  box.querySelectorAll('[data-individual-type]').forEach(function(group){
   group.hidden=group.dataset.individualType!==type.value;
   group.querySelectorAll('input').forEach(function(input){input.disabled=group.hidden;input.required=!group.hidden;});
  });
 }
 type.addEventListener('change',sync);
 document.getElementById('probationaryTemplateToggle')?.addEventListener('change',function(){queueMicrotask(sync);});
 document.addEventListener('DOMContentLoaded',function(){queueMicrotask(sync);},{once:true});
 sync();
})();
</script>
