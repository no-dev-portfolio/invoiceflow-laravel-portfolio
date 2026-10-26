'use strict';
const items=document.getElementById('invoice-items');
if(items){
 let next=Math.max(-1,...Array.from(items.querySelectorAll('input[name]')).map(input=>Number(input.name.match(/items\[(\d+)\]/)?.[1]??-1)))+1;
 document.getElementById('add-item').addEventListener('click',()=>{
  if(items.children.length>=20){window.alert('明細は20行までです。');return;}
  const row=document.getElementById('item-template').content.cloneNode(true);
  row.querySelectorAll('[data-field]').forEach(input=>{input.name=`items[${next}][${input.dataset.field}]`;});next++;items.append(row);
 });
 items.addEventListener('click',event=>{if(event.target.closest('.remove-item')) event.target.closest('.item-row').remove();});
}
document.querySelectorAll('form[data-confirm]').forEach(form=>form.addEventListener('submit',event=>{if(!window.confirm(form.dataset.confirm)) event.preventDefault();}));
document.getElementById('print-invoice')?.addEventListener('click',()=>window.print());
