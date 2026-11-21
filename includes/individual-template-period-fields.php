<?php $periodTemplate=$tmpl ?? []; ?>
<div class="row g-3 mb-3" id="individualTemplatePeriod">
    <div class="col-12"><strong>Employee evaluation period</strong><p class="form-text mb-0">Publishing this form makes it available to eligible employees. No department Start Cycle is required. Revision and Effective Date are separate document dates.</p></div>
    <div class="col-md-6" data-individual-type="Annual"><label for="assessmentFrom" class="form-label">Period From</label><input id="assessmentFrom" class="form-control" name="assessment_from" type="date" value="<?php echo e($periodTemplate['assessment_from'] ?? date('Y').'-01-01'); ?>"></div>
    <div class="col-md-6" data-individual-type="Annual"><label for="assessmentTo" class="form-label">Period To</label><input id="assessmentTo" class="form-control" name="assessment_to" type="date" value="<?php echo e($periodTemplate['assessment_to'] ?? date('Y').'-12-31'); ?>"><div class="form-text">This period is used for every targeted Regular employee.</div></div>
</div>
<script>
(function(){
 const type=document.getElementById('inputEvalType');
 const box=document.getElementById('individualTemplatePeriod');
 function sync(){
  box.hidden=!['Annual','Quarterly'].includes(type.value);
  const toggle=document.getElementById('probationaryTemplateToggle');
  if(toggle) toggle.closest('.form-check').hidden=!['Initial','Final'].includes(type.value);
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
