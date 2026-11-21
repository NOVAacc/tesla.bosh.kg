const labels={service:'Ремонт и запчасти',adapters:'Разъёмы и переходники',charging:'Зарядные станции',software:'Приложение и ПО',battery:'Батарея и пробег',home:'Домашняя зарядка',buying:'Покупка и растаможка',other:'Другие вопросы'};
const $=id=>document.getElementById(id),state={items:[],csrf:'',reordering:false};
for(const [key,name] of Object.entries(labels)){const opt=document.createElement('option');opt.value=key;opt.textContent=name;$('faqCategory').append(opt)}
function el(tag,value,cls){const node=document.createElement(tag);if(value!==undefined)node.textContent=value;if(cls)node.className=cls;return node}
function status(message){$('faqStatus').textContent=message}
function reset(){$('faqForm').reset();$('faqId').value='';$('faqOrder').value=100;$('faqPublished').checked=true;$('editorTitle').textContent='Новый вопрос';$('faqSave').textContent='Сохранить вопрос'}
function edit(item){$('faqId').value=item.id;$('faqCategory').value=item.category;$('faqQuestion').value=item.question;$('faqAnswer').value=item.answer;$('faqOrder').value=item.sort_order;$('faqPublished').checked=Number(item.is_published)===1;$('editorTitle').textContent='Редактировать вопрос №'+item.id;$('faqSave').textContent='Сохранить изменения';$('faqForm').scrollIntoView({behavior:'smooth',block:'start'})}
const cards=$('faqCards');
let draggedId=null;
let dropTarget=null;

function clearDropTarget(){
  dropTarget?.classList.remove('drop-before','drop-after');
  dropTarget=null;
}

function clearDrag(){
  clearDropTarget();
  cards.querySelector('.is-dragging')?.classList.remove('is-dragging');
  draggedId=null;
}

function render(){
  clearDrag();
  const term=$('faqFilter').value.trim().toLowerCase();
  const items=state.items.filter(item=>(item.question+' '+item.answer).toLowerCase().includes(term));
  const canReorder=!term&&!state.reordering&&items.length>1;
  $('faqReorderHint').textContent=term
    ? 'Очистите поиск, чтобы изменить порядок вопросов.'
    : 'Перетащите вопрос за заголовок или используйте стрелки. Порядок сохраняется автоматически.';

  cards.replaceChildren(...items.map((item,index)=>{
    const id=String(item.id);
    const card=el('article',undefined,'faq-admin-card'+(!Number(item.is_published)?' hidden':''));
    card.dataset.id=id;
    const heading=el('div',undefined,'faq-card-heading');
    const title=el('h3',item.question);
    if(canReorder){
      const handle=el('span','⠿','faq-drag-handle');
      heading.draggable=true;
      heading.title='Перетащить вопрос';
      handle.setAttribute('aria-hidden','true');
      heading.addEventListener('dragstart',event=>{
        draggedId=id;
        event.dataTransfer.setData('text/plain',id);
        event.dataTransfer.effectAllowed='move';
        card.classList.add('is-dragging');
      });
      heading.addEventListener('dragend',clearDrag);
      heading.append(handle);
    }
    heading.append(title);
    card.append(
      el('div',(labels[item.category]||item.category)+' · '+(Number(item.is_published)?'Опубликован':'Скрыт')+' · Порядок '+item.sort_order,'meta'),
      heading,
      el('p',item.answer.length>260?item.answer.slice(0,260)+'…':item.answer)
    );
    const actions=el('div',undefined,'actions');
    const editButton=el('button','Изменить','edit');
    const deleteButton=el('button','Удалить','delete');
    editButton.type=deleteButton.type='button';
    editButton.disabled=deleteButton.disabled=state.reordering;
    editButton.onclick=()=>edit(item);
    deleteButton.onclick=()=>remove(item);
    actions.append(editButton,deleteButton);
    if(!term){
      for(const [offset,icon,label] of [[-1,'↑','Поднять вопрос'],[1,'↓','Опустить вопрос']]){
        const button=el('button',icon,'move');
        button.type='button';
        button.setAttribute('aria-label',label);
        button.disabled=!canReorder||!items[index+offset];
        button.onclick=()=>moveQuestion(id,String(items[index+offset].id),offset>0);
        actions.append(button);
      }
    }
    card.append(actions);
    return card;
  }));
  if(!items.length)cards.append(el('p','Вопросы не найдены.'));
}

function dropAfter(event,card){
  const bounds=card.getBoundingClientRect();
  return event.clientY>bounds.top+bounds.height/2;
}

cards.addEventListener('dragover',event=>{
  if(!draggedId)return;
  const target=event.target.closest('[data-id]');
  clearDropTarget();
  if(!target||target.dataset.id===draggedId)return;
  event.preventDefault();
  event.dataTransfer.dropEffect='move';
  target.classList.add(dropAfter(event,target)?'drop-after':'drop-before');
  dropTarget=target;
});

cards.addEventListener('dragleave',event=>{
  if(!dropTarget?.contains(event.relatedTarget))clearDropTarget();
});

cards.addEventListener('drop',event=>{
  const target=event.target.closest('[data-id]');
  if(!draggedId||!target)return;
  event.preventDefault();
  const id=draggedId;
  clearDrag();
  if(id!==target.dataset.id)moveQuestion(id,target.dataset.id,dropAfter(event,target));
});

async function moveQuestion(id,targetId,after){
  if(state.reordering||$('faqFilter').value.trim()||$('faqSave').disabled)return;
  const currentIds=state.items.map(item=>String(item.id));
  const ids=currentIds.filter(itemId=>itemId!==id);
  ids.splice(ids.indexOf(targetId)+Number(after),0,id);
  if(ids.every((itemId,index)=>itemId===currentIds[index]))return;

  state.reordering=true;
  $('faqSave').disabled=true;
  render();
  status('Сохраняем порядок…');
  try{
    const result=await write('PATCH',{ids,previous_ids:currentIds});
    const byId=new Map(state.items.map(item=>[String(item.id),item]));
    const currentEdit=$('faqId').value;
    const updateEditor=byId.has(currentEdit)&&Number($('faqOrder').value)===Number(byId.get(currentEdit).sort_order);
    state.items=ids.map((itemId,index)=>({...byId.get(itemId),sort_order:index*result.step}));
    if(updateEditor)$('faqOrder').value=state.items.find(item=>String(item.id)===currentEdit).sort_order;
    status('Порядок сохранён');
  }catch(error){
    await load();
    status('Не удалось сохранить порядок. Проверьте список и повторите. '+error.message);
  }finally{
    state.reordering=false;
    $('faqSave').disabled=false;
    render();
  }
}
async function load(){status('Загрузка…');try{const r=await fetch('/api/faq.php?admin=1',{cache:'no-store'});if(!r.ok)throw Error('HTTP '+r.status);const data=await r.json();state.items=data.items;state.csrf=data.csrf;render();status('Вопросов в базе: '+state.items.length)}catch(e){status('Не удалось загрузить FAQ. Проверьте, что миграция базы выполнена. '+e.message)}}
async function write(method,body){const r=await fetch('/api/faq.php',{method,headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({...body,csrf:state.csrf})});if(!r.ok)throw Error('HTTP '+r.status);return r.json()}
$('faqForm').addEventListener('submit',async e=>{e.preventDefault();const id=Number($('faqId').value);const data={category:$('faqCategory').value,question:$('faqQuestion').value,answer:$('faqAnswer').value,sort_order:Number($('faqOrder').value),is_published:$('faqPublished').checked};$('faqSave').disabled=true;try{await write(id?'PUT':'POST',id?{...data,id}:data);reset();await load();status('Изменения сохранены')}catch(err){status('Ошибка сохранения: '+err.message)}finally{$('faqSave').disabled=false}});
async function remove(item){if(!confirm('Удалить вопрос без возможности восстановления?\n'+item.question))return;try{await write('DELETE',{id:Number(item.id)});if(Number($('faqId').value)===Number(item.id))reset();await load();status('Вопрос удалён')}catch(e){status('Не удалось удалить: '+e.message)}}
$('faqCancel').onclick=reset;$('faqFilter').addEventListener('input',render);load();