(()=>{
  const q=(s,c=document)=>c.querySelector(s);
  const qa=(s,c=document)=>[...c.querySelectorAll(s)];

  q('[data-sidebar-toggle]')?.addEventListener('click',()=>q('#sidebar')?.classList.toggle('open'));
  document.addEventListener('click',e=>{
    const sb=q('#sidebar');
    if(sb?.classList.contains('open')&&!sb.contains(e.target)&&!e.target.closest('[data-sidebar-toggle]')) sb.classList.remove('open');
  });

  qa('[data-fill-login]').forEach(btn=>btn.addEventListener('click',()=>{
    const email=q('input[name=email]');
    if(email) email.value=btn.dataset.fillLogin;
  }));

  qa('form[data-confirm]').forEach(form=>form.addEventListener('submit',e=>{
    const message=form.dataset.confirm||'Confirmar esta ação?';
    if(!window.confirm(message)) e.preventDefault();
  }));

  qa('[data-filter-table]').forEach(box=>{
    const search=q('[data-filter-search]',box);
    const status=q('[data-filter-status]',box);
    const rows=qa('[data-filter-row]',box);
    const empty=q('[data-filter-empty]',box);
    const apply=()=>{
      const term=(search?.value||'').trim().toLocaleLowerCase('pt-BR');
      const st=status?.value||'';
      let visible=0;
      rows.forEach(row=>{
        const okTerm=!term||(row.dataset.search||'').includes(term);
        const okStatus=!st||row.dataset.status===st;
        row.hidden=!(okTerm&&okStatus);
        if(!row.hidden) visible++;
      });
      if(empty) empty.hidden=visible!==0;
    };
    search?.addEventListener('input',apply);
    status?.addEventListener('change',apply);
  });

  const onlyDigits=value=>(value||'').replace(/\D/g,'');
  const applyMask=(kind,value)=>{
    let d=onlyDigits(value);
    if(kind==='cpf'){
      d=d.slice(0,11);
      return d.replace(/(\d{3})(\d)/,'$1.$2').replace(/(\d{3})(\d)/,'$1.$2').replace(/(\d{3})(\d{1,2})$/,'$1-$2');
    }
    if(kind==='cnpj'){
      d=d.slice(0,14);
      return d.replace(/^(\d{2})(\d)/,'$1.$2').replace(/^(\d{2})\.(\d{3})(\d)/,'$1.$2.$3').replace(/\.(\d{3})(\d)/,'.$1/$2').replace(/(\d{4})(\d{1,2})$/,'$1-$2');
    }
    if(kind==='cep'){
      d=d.slice(0,8);
      return d.replace(/(\d{5})(\d)/,'$1-$2');
    }
    if(kind==='phone'){
      d=d.slice(0,11);
      if(d.length<=10) return d.replace(/^(\d{2})(\d)/,'($1) $2').replace(/(\d{4})(\d)/,'$1-$2');
      return d.replace(/^(\d{2})(\d)/,'($1) $2').replace(/(\d{5})(\d)/,'$1-$2');
    }
    if(kind==='rg'){
      const raw=(value||'').toUpperCase().replace(/[^0-9A-Z]/g,'').slice(0,9);
      return raw.replace(/^(.{2})(.)/,'$1.$2').replace(/^(.{2})\.(.{3})(.)/,'$1.$2.$3').replace(/(.{3})(.)$/,'$1-$2');
    }
    if(kind==='cpfcnpj') return applyMask(d.length<=11?'cpf':'cnpj',d);
    return value;
  };

  qa('[data-mask]').forEach(input=>{
    const update=()=>{ input.value=applyMask(input.dataset.mask,input.value); };
    input.addEventListener('input',update);
    update();
  });

  qa('input[type="password"]').forEach(input=>{
    const button=document.createElement('button');
    button.type='button';
    button.className='password-toggle';
    button.setAttribute('aria-label','Mostrar senha');
    button.textContent='👁';
    input.parentElement?.classList.add('has-password-toggle');
    input.insertAdjacentElement('afterend',button);
    button.addEventListener('click',()=>{
      const show=input.type==='password';
      input.type=show?'text':'password';
      button.setAttribute('aria-label',show?'Ocultar senha':'Mostrar senha');
      button.textContent=show?'🙈':'👁';
      input.focus();
    });
  });

  qa('form.register-form').forEach(form=>form.addEventListener('submit',event=>{
    const categories=q('.category-checks',form);
    if(categories&&!q('input[type="checkbox"]:checked',categories)){
      event.preventDefault();
      const first=q('input[type="checkbox"]',categories);
      categories.scrollIntoView({behavior:'smooth',block:'center'});
      first?.focus();
      window.alert('Selecione pelo menos uma função de interesse.');
    }
  }));

  qa('input[name="acceptance_mode"]').forEach(input=>input.addEventListener('change',()=>{
    qa('.choice-card').forEach(card=>card.classList.toggle('selected',!!q('input',card)?.checked));
  }));
})();
