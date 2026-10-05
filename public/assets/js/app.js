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

  const bindPasswordToggle=(input,button)=>{
    if(!input||!button||button.dataset.passwordBound==='1') return;
    button.dataset.passwordBound='1';
    button.addEventListener('click',()=>{
      const show=input.type==='password';
      input.type=show?'text':'password';
      button.setAttribute('aria-label',show?'Ocultar senha':'Mostrar senha');
      button.setAttribute('aria-pressed',show?'true':'false');
      button.classList.toggle('is-visible',show);
      input.focus({preventScroll:true});
    });
  };

  qa('.password-field').forEach(wrapper=>{
    bindPasswordToggle(q('input',wrapper),q('[data-password-toggle]',wrapper));
  });

  qa('input[type="password"]').forEach(input=>{
    if(input.closest('.password-field')) return;

    const wrapper=document.createElement('span');
    wrapper.className='password-field';
    input.parentNode?.insertBefore(wrapper,input);
    wrapper.appendChild(input);

    const button=document.createElement('button');
    button.type='button';
    button.className='password-toggle';
    button.dataset.passwordToggle='';
    button.setAttribute('aria-label','Mostrar senha');
    button.setAttribute('aria-pressed','false');
    button.innerHTML='<svg class="tp-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.75"/></svg>';
    wrapper.appendChild(button);
    bindPasswordToggle(input,button);
  });

  qa('[data-category-modal]').forEach(modal=>{
    const openButton=q('[data-category-modal-open]');
    const form=q('[data-category-form]',modal);
    const input=q('input[name="name"]',form);
    const submit=q('[data-category-submit]',form);
    const feedback=q('[data-category-feedback]',form);
    const select=q('[data-category-select]');
    const notice=q('[data-category-created-notice]');
    let previousFocus=null;

    const setFeedback=(message='')=>{
      if(!feedback) return;
      feedback.textContent=message;
      feedback.hidden=!message;
    };

    const openModal=()=>{
      previousFocus=document.activeElement;
      setFeedback('');
      modal.hidden=false;
      document.body.classList.add('modal-open');
      window.setTimeout(()=>input?.focus(),0);
    };

    const closeModal=()=>{
      modal.hidden=true;
      document.body.classList.remove('modal-open');
      form?.reset();
      setFeedback('');
      previousFocus?.focus?.();
    };

    openButton?.addEventListener('click',openModal);
    qa('[data-category-modal-close]',modal).forEach(button=>button.addEventListener('click',closeModal));
    modal.addEventListener('click',event=>{ if(event.target===modal) closeModal(); });
    document.addEventListener('keydown',event=>{
      if(event.key==='Escape'&&!modal.hidden) closeModal();
    });

    form?.addEventListener('submit',async event=>{
      event.preventDefault();
      const name=(input?.value||'').trim();
      if(name.length<2){
        setFeedback('Informe um nome de categoria com pelo menos 2 caracteres.');
        input?.focus();
        return;
      }

      submit.disabled=true;
      setFeedback('');
      try{
        const response=await fetch(form.action,{
          method:'POST',
          body:new FormData(form),
          headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}
        });
        const raw=await response.text();
        let payload={};
        try{ payload=raw?JSON.parse(raw):{}; }catch(_){ payload={}; }

        if(!response.ok||!payload.ok){
          const message=payload.error||(response.status===419
            ? 'Sua sessão expirou. Atualize a página e tente novamente.'
            : 'Não foi possível cadastrar a categoria.');
          throw new Error(message);
        }

        const category=payload.category||{};
        if(!category.id||!category.name||!select) throw new Error('A categoria foi salva, mas não foi possível selecioná-la.');

        let option=[...select.options].find(item=>String(item.value)===String(category.id));
        if(!option){
          option=new Option(String(category.name),String(category.id));
          select.add(option);
        }else{
          option.textContent=String(category.name);
        }

        select.value=String(category.id);
        select.dispatchEvent(new Event('change',{bubbles:true}));

        if(notice){
          notice.textContent='Categoria “'+String(category.name)+'” cadastrada e selecionada.';
          notice.hidden=false;
          window.setTimeout(()=>{ notice.hidden=true; },5000);
        }

        modal.hidden=true;
        document.body.classList.remove('modal-open');
        form.reset();
        setFeedback('');
        select.focus();
      }catch(error){
        setFeedback(error?.message||'Não foi possível cadastrar a categoria.');
        input?.focus();
      }finally{
        submit.disabled=false;
      }
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
