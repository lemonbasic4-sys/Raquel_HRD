<style>
.template-position-display{display:flex;flex-wrap:wrap;align-items:center;gap:6px;min-height:42px;max-height:112px;overflow-y:auto;padding:8px 10px;border:1px solid #d4dfce;border-radius:8px;background:#f8fbf5}
.template-position-display span{display:inline-block;padding:4px 8px;border-radius:6px;background:#e7f0df;color:#244b15;font-size:.76rem;font-weight:600;line-height:1.4;white-space:normal}
</style>
<script>
document.addEventListener('DOMContentLoaded',function(){
 const select=document.getElementById('inputTargetPosition');
 const display=document.getElementById('targetPositionDisplay');
 if(!select||!display)return;
 function render(){
  display.replaceChildren();
  const positions=Array.from(select.options).filter(option=>!option.hidden&&option.selected);
  positions.forEach(function(option){const chip=document.createElement('span');chip.textContent=option.textContent;display.appendChild(chip);});
  if(!positions.length)display.textContent='No available positions';
 }
 render();
 document.getElementById('inputTargetDept')?.addEventListener('change',()=>queueMicrotask(render));
 document.getElementById('inputEvalType')?.addEventListener('change',()=>queueMicrotask(render));
 new MutationObserver(render).observe(select,{childList:true,subtree:true,attributes:true,attributeFilter:['hidden','selected']});
});
</script>
