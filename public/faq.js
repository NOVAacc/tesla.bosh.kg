const labels={all:'Все вопросы',service:'Ремонт и запчасти',adapters:'Разъёмы и переходники',charging:'Зарядные станции',software:'Приложение и программное обеспечение',battery:'Батарея и запас хода',home:'Домашняя зарядка',buying:'Покупка и растаможка',other:'Другие вопросы'};
const order=Object.keys(labels);
const categories=document.getElementById('categories'),list=document.getElementById('faqItems'),search=document.getElementById('search'),heading=document.getElementById('currentCategory'),count=document.getElementById('resultCount'),expand=document.getElementById('expandAll');
let items=[],category='all';
function element(tag,text,cls){const e=document.createElement(tag);if(text!==undefined)e.textContent=text;if(cls)e.className=cls;return e}
function selectCategory(key){category=key;render()}
function render(){
 const term=search.value.trim().toLocaleLowerCase('ru');
 const visible=items.filter(item=>(category==='all'||item.category===category)&&(!term||(item.question+' '+item.answer).toLocaleLowerCase('ru').includes(term)));
 categories.replaceChildren(...order.map(key=>{
  const filtered=items.filter(item=>key==='all'||item.category===key);
  if(!filtered.length)return null;
  const btn=element('button',undefined,'cat'+(key===category?' active':''));btn.type='button';btn.setAttribute('aria-pressed',String(key===category));
  btn.append(element('span',labels[key]),element('span',String(filtered.length)));btn.addEventListener('click',()=>selectCategory(key));return btn;
 }).filter(Boolean));
 heading.textContent=labels[category];count.textContent=visible.length+' '+plural(visible.length,'вопрос','вопроса','вопросов');
 if(!visible.length){list.replaceChildren(element('p','По вашему запросу ничего не найдено.','empty'));return}
 list.replaceChildren(...visible.map(item=>{const d=element('details',undefined,'item'),s=element('summary',item.question),a=element('div',item.answer,'answer');d.append(s,a);return d}));
 expand.textContent='Раскрыть все';
}
function plural(n,one,two,many){return n%10===1&&n%100!==11?one:n%10>=2&&n%10<=4&&(n%100<12||n%100>14)?two:many}
search.addEventListener('input',render);
expand.addEventListener('click',()=>{const all=[...list.querySelectorAll('details')];const openAll=all.some(d=>!d.open);all.forEach(d=>d.open=openAll);expand.textContent=openAll?'Свернуть все':'Раскрыть все'});
(async()=>{try{const response=await fetch('/api/faq.php');if(!response.ok)throw Error('HTTP '+response.status);const data=await response.json();if(!Array.isArray(data.items))throw Error('Invalid FAQ');items=data.items;render()}catch{list.replaceChildren(element('p','Вопросы временно недоступны. Попробуйте обновить страницу.','empty'));count.textContent=''}})();