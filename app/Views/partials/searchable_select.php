<?php
/**
 * Turns an existing <select> into a search box plus dropdown.
 * Usage (after the select): <?= view('partials/searchable_select', ['selectId' => 'receiptCustomer', 'placeholder' => 'Type to search customer...']) ?>
 * The select stays the source of truth (value, change event, form submit, required).
 */
$selectId = $selectId ?? '';
$placeholder = $placeholder ?? 'Type to search...';
?>
<div class="position-relative ss-wrap" data-for="<?= esc($selectId, 'attr') ?>">
  <input type="text" class="form-control ss-input" autocomplete="off" placeholder="<?= esc($placeholder, 'attr') ?>">
  <div class="list-group shadow position-absolute w-100 ss-list" style="z-index:1050;max-height:260px;overflow:auto;display:none"></div>
</div>
<script>
(function(){
  var wrap=document.currentScript.previousElementSibling;
  var sel=document.getElementById(wrap.dataset.for),inp=wrap.querySelector('.ss-input'),list=wrap.querySelector('.ss-list');
  if(!sel)return;
  // Hide the select visually but keep it in the form so "required" still validates.
  sel.style.cssText='position:absolute;opacity:0;height:1px;width:1px;pointer-events:none;';sel.tabIndex=-1;
  var active=-1,items=[];
  function showSelected(){var o=sel.options[sel.selectedIndex];inp.value=o&&o.value!==''?o.text:'';}
  function close(){list.style.display='none';active=-1;showSelected();}
  function build(q){
    q=(q||'').trim().toLowerCase();items=[];list.innerHTML='';
    [].slice.call(sel.options).forEach(function(o){
      if(o.value===''||o.disabled||o.hidden)return;
      if(q&&o.text.toLowerCase().indexOf(q)===-1)return;
      var b=document.createElement('button');b.type='button';b.className='list-group-item list-group-item-action py-1';b.textContent=o.text;
      b.onmousedown=function(e){e.preventDefault();pick(o.value);};
      list.appendChild(b);items.push({b:b,v:o.value});
    });
    if(!items.length){var n=document.createElement('div');n.className='list-group-item text-muted py-1';n.textContent='No matching result';list.appendChild(n);}
    list.style.display='block';active=-1;
  }
  function pick(v){sel.value=v;sel.dispatchEvent(new Event('change',{bubbles:true}));list.style.display='none';showSelected();inp.blur();}
  function mark(){items.forEach(function(it,i){it.b.classList.toggle('active',i===active);});if(items[active])items[active].b.scrollIntoView({block:'nearest'});}
  inp.addEventListener('focus',function(){inp.select();build('');});
  inp.addEventListener('input',function(){build(inp.value);});
  inp.addEventListener('blur',close);
  inp.addEventListener('keydown',function(e){
    if(e.key==='ArrowDown'){e.preventDefault();if(list.style.display==='none')build(inp.value);active=Math.min(items.length-1,active+1);mark();}
    else if(e.key==='ArrowUp'){e.preventDefault();active=Math.max(0,active-1);mark();}
    else if(e.key==='Enter'){e.preventDefault();var it=items[active>=0?active:(items.length===1?0:-1)];if(it)pick(it.v);}
    else if(e.key==='Escape'){close();inp.blur();}
  });
  var d=Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype,'value');
  Object.defineProperty(sel,'value',{get:function(){return d.get.call(sel);},set:function(v){d.set.call(sel,v);showSelected();},configurable:true});
  sel.addEventListener('change',showSelected);
  if(sel.form)sel.form.addEventListener('reset',function(){setTimeout(showSelected,0);});
  // A required select that is empty: show the browser message on the visible box instead.
  sel.addEventListener('invalid',function(){inp.focus();});
  showSelected();
})();
</script>
