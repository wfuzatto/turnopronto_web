/* Landing UX V3: controls wired to real vacancies from the server. */
(() => {
  'use strict';
  const root=document.querySelector('.tp3');
  if(!root) return;
  const form=root.querySelector('[data-tp3-search]');
  const list=root.querySelector('[data-tp3-list]');
  if(!form || !list) return;
  const role=form.querySelector('[name="role"]');
  const city=form.querySelector('[name="city"]');
  const date=form.querySelector('[name="date"]');
  const count=root.querySelector('[data-tp3-count]');
  const empty=root.querySelector('[data-tp3-empty]');
  const featureEmpty=root.querySelector('[data-tp3-feature-empty]');
  const sort=root.querySelector('[data-tp3-sort]');
  const rows=Array.from(list.querySelectorAll('[data-tp3-list-job]'));
  const features=Array.from(root.querySelectorAll('[data-tp3-feature]'));
  const categories=Array.from(root.querySelectorAll('[data-tp3-category]'));
  const cityTrigger=root.querySelector('[data-tp3-focus-city]');
  let selectedCategory='';
  const normalized=value=>String(value??'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLocaleLowerCase('pt-BR').trim();
  const matches=(element,roleText,cityText,dateText,categoryText)=>{
    const search=normalized(element.dataset.search);
    const locality=normalized(element.dataset.city);
    const category=normalized(element.dataset.category);
    return (!roleText || search.includes(roleText))
      &&(!cityText || locality.includes(cityText))
      &&(!dateText || element.dataset.date===dateText)
      &&(!categoryText || category===categoryText);
  };
  const apply=()=>{
    const roleText=normalized(role?.value);
    const cityText=normalized(city?.value);
    const dateText=date?.value||'';
    const categoryText=normalized(selectedCategory);
    let visible=0,visibleFeatures=0;
    for(const row of rows){
      row.hidden=!matches(row,roleText,cityText,dateText,categoryText);
      if(!row.hidden) visible++;
    }
    for(const item of features){
      item.hidden=!matches(item,roleText,cityText,dateText,categoryText);
      if(!item.hidden) visibleFeatures++;
    }
    if(count) count.textContent=String(visible);
    if(empty) empty.hidden=visible!==0;
    if(featureEmpty) featureEmpty.hidden=visibleFeatures!==0;
    if(cityTrigger){
      const caption=cityTrigger.querySelector('span');
      if(caption) caption.textContent=city?.value?.trim()||'Sua cidade';
    }
  };
  const updateCategory=category=>{
    selectedCategory=category||'';
    for(const button of categories){
      const active=button.dataset.tp3Category===selectedCategory;
      button.classList.toggle('active',active);
      button.setAttribute('aria-pressed',String(active));
    }
    apply();
  };
  const sortRows=()=>{
    const mode=sort?.value||'data';
    const byTitle=(a,b)=>normalized(a.dataset.title).localeCompare(normalized(b.dataset.title),'pt-BR');
    rows.sort((a,b)=>{
      if(mode==='valor') return (Number(b.dataset.price)||0)-(Number(a.dataset.price)||0) || (a.dataset.date||'').localeCompare(b.dataset.date||'');
      if(mode==='titulo') return byTitle(a,b);
      return (a.dataset.date||'').localeCompare(b.dataset.date||'') || byTitle(a,b);
    });
    rows.forEach(row=>list.appendChild(row));
  };
  form.addEventListener('submit',event=>{
    event.preventDefault();
    apply();
    root.querySelector('#todas-vagas')?.scrollIntoView({behavior:'smooth',block:'start'});
  });
  role?.addEventListener('input',apply);
  city?.addEventListener('input',apply);
  date?.addEventListener('change',apply);
  categories.forEach(button=>{
    button.addEventListener('click',()=>{
      updateCategory(button.dataset.tp3Category||'');
    });
  });
  cityTrigger?.addEventListener('click',()=>{
    city?.focus();
    city?.scrollIntoView({behavior:'smooth',block:'center'});
  });
  sort?.addEventListener('change',sortRows);
  root.querySelectorAll('[data-tp3-view]').forEach(button=>{
    button.addEventListener('click',()=>{
      const mode=button.dataset.tp3View||'list';
      list.classList.toggle('is-grid',mode==='grid');
      root.querySelectorAll('[data-tp3-view]').forEach(item=>{
        const active=item===button;
        item.classList.toggle('active',active);
        item.setAttribute('aria-pressed',String(active));
      });
    });
  });
  root.querySelector('[data-tp3-clear]')?.addEventListener('click',()=>{
    if(role) role.value='';
    if(city) city.value='';
    if(date) date.value='';
    updateCategory('');
    if(sort) sort.value='data';
    sortRows();
    role?.focus();
  });
  sortRows();
  apply();
})();
