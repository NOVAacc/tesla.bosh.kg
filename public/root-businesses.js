const container=document.getElementById('businessReviewCards');
const status=document.getElementById('businessReviewStatus');
const nextButton=document.getElementById('businessReviewNext');
const refreshButton=document.getElementById('businessReviewRefresh');
const apiUrl='/api/root-businesses.php';
let csrf='',next=null,loading=false;

function element(tag,text){
 const node=document.createElement(tag);
 node.textContent=text;
 return node;
}

function renderApplication(item){
 const card=document.createElement('article');
 card.className='card';
 card.append(element('h3',item.name),element('p',item.category));
 for(const value of [item.address,item.description,item.phone,item.whatsapp]){
  if(value)card.append(element('p',value));
 }
 const hasPoint=item.latitude!==null&&item.longitude!==null&&!!item.address;
 if(hasPoint){
  const map=element('a','Посмотреть точку на карте');
  const lat=encodeURIComponent(item.latitude),lng=encodeURIComponent(item.longitude);
  map.href='https://www.openstreetmap.org/?mlat='+lat+'&mlon='+lng+'#map=17/'+lat+'/'+lng;
  map.target='_blank';
  map.rel='noopener noreferrer';
  card.append(map);
 }else card.append(element('p','В заявке не указан адрес или точка на карте.'));
 const form=document.createElement('form');
 const label=element('label','Комментарий для доработки');
 const note=document.createElement('textarea');
 note.maxLength=500;
 note.rows=2;
 label.append(document.createElement('br'),note);
 const approve=element('button','Одобрить');
 approve.type='button';
 approve.disabled=!hasPoint;
 const returnButton=element('button','На доработку');
 returnButton.type='submit';
 const message=element('p','');
 message.setAttribute('role','status');
 let submitting=false;
 async function review(decision){
  if(submitting)return;
  if(decision==='rejected'&&!note.value.trim()){
   message.textContent='Напишите, что нужно исправить.';
   note.focus();
   return;
  }
  submitting=true;
  approve.disabled=returnButton.disabled=true;
  message.textContent='';
  try{
   const response=await fetch(apiUrl,{
    method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({id:Number(item.id),decision,note:note.value.trim(),csrf})
   });
   if(response.status===409){
    message.textContent='Заявка изменилась. Обновите список.';
    return;
   }
   if(!response.ok)throw Error('Review failed');
   card.remove();
   status.textContent=decision==='approved'?'Заявка одобрена.':'Заявка возвращена на доработку.';
  }catch{
   message.textContent='Не удалось сохранить решение. Попробуйте ещё раз.';
  }finally{
   submitting=false;
   approve.disabled=!hasPoint;
   returnButton.disabled=false;
  }
 }
 form.addEventListener('submit',event=>{event.preventDefault();void review('rejected');});
 approve.addEventListener('click',()=>void review('approved'));
 const actions=document.createElement('div');
 actions.append(approve,' ',returnButton);
 form.append(label,actions,message);
 card.append(form);
 return card;
}

async function loadApplications(append=false){
 if(loading)return;
 loading=true;
 refreshButton.disabled=nextButton.disabled=true;
 status.textContent='Загружаем заявки…';
 try{
  const response=await fetch(append&&next?apiUrl+'?before='+encodeURIComponent(next):apiUrl,{cache:'no-store'});
  if(!response.ok)throw Error('Request failed');
  const data=await response.json();
  csrf=data.csrf;
  next=data.next;
  if(!append)container.replaceChildren();
  container.append(...data.items.map(renderApplication));
  nextButton.hidden=!next;
  status.textContent=container.childElementCount?'':'Заявок на проверке нет.';
 }catch{
  status.textContent='Не удалось загрузить заявки. Попробуйте обновить список.';
 }finally{
  loading=false;
  refreshButton.disabled=nextButton.disabled=false;
 }
}
refreshButton.addEventListener('click',()=>void loadApplications());
nextButton.addEventListener('click',()=>void loadApplications(true));
void loadApplications();
