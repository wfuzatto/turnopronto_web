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

  // CEP: preenche automaticamente endereço, cidade e UF.
  qa('input[name="postal_code"], input[data-mask="cep"]').forEach(cepInput=>{
    const form=cepInput.closest('form')||document;
    const address=q('input[name="address"]',form);
    const city=q('input[name="city"]',form);
    const state=q('input[name="state"]',form);
    if(!address&&!city&&!state) return;

    let timer=0;
    let lastCep='';
    let status=cepInput.parentElement?.querySelector('[data-cep-status]');
    if(!status){
      status=document.createElement('small');
      status.className='field-note cep-lookup-status';
      status.dataset.cepStatus='';
      cepInput.insertAdjacentElement('afterend',status);
    }

    const lookup=async()=>{
      const cep=onlyDigits(cepInput.value).slice(0,8);
      if(cep.length!==8 || cep===lastCep) return;
      lastCep=cep;
      status.textContent='Buscando endereço pelo CEP...';
      status.classList.remove('error','success');
      try{
        const response=await fetch((window.TP_BASE||'')+'/api/v1/cep/'+cep,{
          headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'},
          cache:'no-store'
        });
        const payload=await response.json().catch(()=>({}));
        if(!response.ok||!payload.ok) throw new Error(payload.error||'CEP não encontrado.');
        const data=payload.data||{};
        const street=[data.street,data.neighborhood].filter(Boolean).join(' · ');
        if(address&&street) address.value=street;
        if(city&&data.city) city.value=data.city;
        if(state&&data.state) state.value=data.state.toUpperCase();
        status.textContent='Endereço encontrado. Complete o número/complemento se necessário.';
        status.classList.add('success');
        address?.focus();
      }catch(error){
        status.textContent=error?.message||'Não foi possível consultar o CEP. Preencha o endereço manualmente.';
        status.classList.add('error');
      }
    };

    const schedule=()=>{
      window.clearTimeout(timer);
      if(onlyDigits(cepInput.value).length===8) timer=window.setTimeout(lookup,300);
    };
    cepInput.addEventListener('input',schedule);
    cepInput.addEventListener('blur',lookup);
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

  // Administração: duplo clique transforma o dado em input; ✓ salva no backend.
  const inlineRoot=q('[data-admin-inline-root]');
  if(inlineRoot){
    const endpoint=inlineRoot.dataset.endpoint||'';
    const csrf=inlineRoot.dataset.csrf||'';
    const activate=card=>{
      if(card.dataset.editing==='1') return;
      card.dataset.editing='1';
      card.classList.add('editing');
      const display=q('[data-inline-display]',card);
      const original=card.dataset.value||'';
      const type=card.dataset.inputType||'text';
      let input;
      if(type==='select'){
        input=document.createElement('select');
        let options={};
        try{options=JSON.parse(card.dataset.options||'{}');}catch(_){}
        Object.entries(options).forEach(([value,label])=>{
          const option=new Option(String(label),String(value),false,String(value)===original);
          input.add(option);
        });
      }else{
        input=document.createElement('input');
        input.type=type;
        input.value=original;
        if(type==='tel') input.inputMode='tel';
      }
      input.className='admin-inline-input';
      input.setAttribute('aria-label','Editar '+(q('small',card)?.textContent||'campo'));

      const save=document.createElement('button');
      save.type='button';
      save.className='admin-inline-save';
      save.textContent='✓';
      save.title='Salvar';
      save.setAttribute('aria-label','Salvar alteração');

      const editor=document.createElement('div');
      editor.className='admin-inline-editor';
      editor.append(input,save);
      display?.setAttribute('hidden','');
      q('.admin-edit-hint',card)?.setAttribute('hidden','');
      card.appendChild(editor);
      input.focus();
      if(input.select) input.select();

      const cancel=()=>{
        editor.remove();
        display?.removeAttribute('hidden');
        q('.admin-edit-hint',card)?.removeAttribute('hidden');
        card.dataset.editing='0';
        card.classList.remove('editing','saving','saved','save-error');
      };

      const submit=async()=>{
        const value=(input.value||'').trim();
        if(value===original){cancel();return;}
        card.classList.add('saving');
        save.disabled=true;
        input.disabled=true;
        try{
          const body=new URLSearchParams({_csrf:csrf,field:card.dataset.field||'',value});
          const response=await fetch(endpoint,{
            method:'POST',
            body,
            headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest','Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'}
          });
          const payload=await response.json().catch(()=>({}));
          if(!response.ok||!payload.ok) throw new Error(payload.error||'Não foi possível salvar a alteração.');
          card.dataset.value=String(payload.data?.value??value);
          if(display) display.textContent=String(payload.data?.value??value)||'—';
          card.classList.remove('saving');
          card.classList.add('saved');
          editor.remove();
          display?.removeAttribute('hidden');
          q('.admin-edit-hint',card)?.removeAttribute('hidden');
          card.dataset.editing='0';
          window.setTimeout(()=>window.location.reload(),450);
        }catch(error){
          card.classList.remove('saving');
          card.classList.add('save-error');
          input.disabled=false;
          save.disabled=false;
          window.alert(error?.message||'Não foi possível salvar.');
          input.focus();
        }
      };

      save.addEventListener('click',submit);
      input.addEventListener('keydown',event=>{
        if(event.key==='Enter'){event.preventDefault();submit();}
        if(event.key==='Escape'){event.preventDefault();cancel();}
      });
    };

    qa('[data-admin-inline-edit]',inlineRoot).forEach(card=>{
      card.addEventListener('dblclick',()=>activate(card));
      card.addEventListener('keydown',event=>{
        if(event.key==='Enter'&&card.dataset.editing!=='1'){event.preventDefault();activate(card);}
      });
    });
  }

  // Check-out: bloqueio visual sincronizado com a trava de 15 minutos do backend.
  qa('[data-checkout-unlock-at]').forEach(box=>{
    const button=q('[data-checkout-button]',box);
    const countdown=q('[data-checkout-countdown]',box);
    const unlockAt=Number(box.dataset.checkoutUnlockAt||0)*1000;
    if(!button||!countdown||!unlockAt) return;
    const tick=()=>{
      const remaining=Math.max(0,unlockAt-Date.now());
      if(remaining<=0){
        button.disabled=false;
        button.removeAttribute('aria-disabled');
        countdown.textContent='Check-out liberado.';
        box.classList.add('checkout-ready');
        return true;
      }
      button.disabled=true;
      button.setAttribute('aria-disabled','true');
      const total=Math.ceil(remaining/1000);
      const min=Math.floor(total/60);
      const sec=total%60;
      countdown.textContent='Tempo mínimo para liberar o check-out: '+String(min).padStart(2,'0')+':'+String(sec).padStart(2,'0');
      return false;
    };
    if(!tick()){
      const interval=window.setInterval(()=>{if(tick())window.clearInterval(interval);},1000);
    }
  });

  const notificationRoot=q('[data-notifications]');
  if(notificationRoot){
    const toggle=q('[data-notifications-toggle]',notificationRoot);
    const panel=q('[data-notifications-panel]',notificationRoot);
    const badge=q('[data-notification-badge]',notificationRoot);
    const summary=q('[data-notification-summary]',notificationRoot);
    const list=q('[data-notification-list]',notificationRoot);
    const knownIds=new Set(
      qa('.notification-item',list).map(link=>{
        const match=(link.getAttribute('href')||'').match(/notificacoes\/(\d+)\/abrir/);
        return match?match[1]:'';
      }).filter(Boolean)
    );
    let audioContext=null;

    const unlockAudio=()=>{
      if(audioContext) return;
      try{
        const Ctx=window.AudioContext||window.webkitAudioContext;
        if(Ctx) audioContext=new Ctx();
      }catch(_){}
    };
    document.addEventListener('pointerdown',unlockAudio,{once:true});
    document.addEventListener('keydown',unlockAudio,{once:true});

    const playPop=()=>{
      try{
        unlockAudio();
        if(!audioContext) return;
        if(audioContext.state==='suspended') audioContext.resume();
        const now=audioContext.currentTime;
        [0,0.08].forEach((offset,index)=>{
          const osc=audioContext.createOscillator();
          const gain=audioContext.createGain();
          osc.type='sine';
          osc.frequency.setValueAtTime(index===0?740:1040,now+offset);
          gain.gain.setValueAtTime(0.0001,now+offset);
          gain.gain.exponentialRampToValueAtTime(0.16,now+offset+0.012);
          gain.gain.exponentialRampToValueAtTime(0.0001,now+offset+0.12);
          osc.connect(gain); gain.connect(audioContext.destination);
          osc.start(now+offset); osc.stop(now+offset+0.13);
        });
      }catch(_){}
    };

    const showNotificationToast=item=>{
      let stack=q('[data-notification-toasts]');
      if(!stack){
        stack=document.createElement('div');
        stack.className='notification-toast-stack';
        stack.dataset.notificationToasts='';
        document.body.appendChild(stack);
      }
      const toast=document.createElement('a');
      toast.className='notification-toast';
      toast.href=item.open_url||((window.TP_BASE||'')+'/notificacoes');
      const icon=document.createElement('span');
      icon.className='notification-toast-icon';
      icon.textContent='!';
      const copy=document.createElement('span');
      const title=document.createElement('strong');
      title.textContent=item.title||'Nova notificação';
      const body=document.createElement('small');
      body.textContent=item.body||'';
      copy.append(title,body);
      const close=document.createElement('button');
      close.type='button';
      close.textContent='×';
      close.setAttribute('aria-label','Fechar');
      close.addEventListener('click',event=>{event.preventDefault();event.stopPropagation();toast.remove();});
      toast.append(icon,copy,close);
      stack.appendChild(toast);
      requestAnimationFrame(()=>toast.classList.add('show'));
      window.setTimeout(()=>{toast.classList.remove('show');window.setTimeout(()=>toast.remove(),250);},8000);
    };

    const signalNew=items=>{
      const fresh=items.filter(item=>!item.read_at && item.id && !knownIds.has(String(item.id)));
      items.forEach(item=>{if(item.id)knownIds.add(String(item.id));});
      if(!fresh.length) return;
      notificationRoot.classList.remove('has-new');
      void notificationRoot.offsetWidth;
      notificationRoot.classList.add('has-new');
      window.setTimeout(()=>notificationRoot.classList.remove('has-new'),4500);
      playPop();
      fresh.slice(0,3).forEach((item,index)=>window.setTimeout(()=>showNotificationToast(item),index*180));
    };

    const setOpen=open=>{
      if(!panel||!toggle) return;
      panel.hidden=!open;
      toggle.setAttribute('aria-expanded',open?'true':'false');
      notificationRoot.classList.toggle('open',open);
    };

    toggle?.addEventListener('click',event=>{
      event.stopPropagation();
      setOpen(panel?.hidden!==false);
    });
    panel?.addEventListener('click',event=>event.stopPropagation());
    document.addEventListener('click',()=>setOpen(false));
    document.addEventListener('keydown',event=>{if(event.key==='Escape')setOpen(false);});

    const iconText=type=>{
      if(type==='matching_shift') return 'V';
      if(type==='invitation') return 'C';
      if(type==='application') return 'P';
      if(type==='application_approved') return '✓';
      if(type==='application_rejected') return '!';
      if(type==='support_ticket'||type==='support_reply') return '?';
      return '•';
    };

    const renderNotifications=data=>{
      if(!data||!badge||!list) return;
      const unread=Number(data.unread||0);
      badge.textContent=unread>99?'99+':String(unread);
      badge.hidden=unread<=0;
      if(summary) summary.textContent=unread+' não lida(s)';

      const items=Array.isArray(data.items)?data.items:[];
      signalNew(items);
      list.replaceChildren();
      if(!items.length){
        const empty=document.createElement('div');
        empty.className='notification-empty';
        empty.textContent='Nenhuma notificação por enquanto.';
        list.appendChild(empty);
        return;
      }

      items.forEach(item=>{
        const link=document.createElement('a');
        link.className='notification-item'+(item.read_at?'':' unread');
        link.href=item.open_url||((window.TP_BASE||'')+'/notificacoes');

        const icon=document.createElement('span');
        icon.className='notification-item-icon notification-item-letter';
        icon.textContent=iconText(item.type||'');

        const copy=document.createElement('span');
        copy.className='notification-item-copy';
        const title=document.createElement('strong');
        title.textContent=item.title||'Notificação';
        const body=document.createElement('span');
        body.textContent=item.body||'';
        const time=document.createElement('small');
        time.textContent=(item.created_at||'').replace(/^([0-9]{4})-([0-9]{2})-([0-9]{2}) /,'$3/$2/$1 ');
        copy.append(title,body,time);
        link.append(icon,copy);
        list.appendChild(link);
      });
    };

    const refreshNotifications=async()=>{
      if(document.hidden) return;
      try{
        const response=await fetch((window.TP_BASE||'')+'/notificacoes/feed',{
          headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'},
          cache:'no-store'
        });
        if(!response.ok) return;
        const payload=await response.json();
        if(payload?.ok) renderNotifications(payload.data);
      }catch(_){}
    };

    window.setInterval(refreshNotifications,20000);
    document.addEventListener('visibilitychange',()=>{if(!document.hidden)refreshNotifications();});
  }
})();
