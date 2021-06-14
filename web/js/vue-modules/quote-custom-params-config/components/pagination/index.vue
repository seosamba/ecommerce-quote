<template>
    <div class="grid_12 alpha omega">
        <ul class="paginator-list list-unstyled grid_9">
            <li class="first-page" v-if="pagerState(sectionName).currentPage !== 1">
                <a href="#" @click.prevent="pageChanged(1)" >
                    <span aria-hidden="true">{{$t('message.first')}}</span>
                </a>
            </li>

            <li class="previous-page" v-if="pagerState(sectionName).currentPage !== 1">
                <a href="#" @click.prevent="pageChanged(pagerState(sectionName).currentPage - 1)" >
                    <span aria-hidden="true">{{$t('message.previous')}}</span>
                </a>
            </li>

            <li v-if="paginationRange[0] > 1">
                ...
            </li>

            <li v-for="n in paginationRange" :class="activePage(n)">
                <a class="pagination__item" href="#" @click.prevent="pageChanged(n)">{{ n }}</a>
            </li>
            <li v-if="paginationRange[paginationRange.length - 1] < lastPage">
                ...
            </li>
            <li class="next-page" v-if="pagerState(sectionName).currentPage < lastPage">
                <a href="#" @click.prevent="pageChanged(pagerState(sectionName).currentPage +1)" >
                    <span aria-hidden="true">{{$t('message.next')}}</span>
                </a>
            </li>
            <li class="last-page" v-if="pagerState(sectionName).currentPage < lastPage">
                <a href="#" @click.prevent="pageChanged(lastPage)" >
                    <span aria-hidden="true">{{$t('message.last')}}</span>
                </a>
            </li>
        </ul>
        <p class="grid_3 mt15px text-right">{{$t('message.total')}} <span class="text-bold"> {{$store.state.pagination[sectionName].totalItems}} {{$t('message.records')}}</span> {{$t('message.found')}}</p>
    </div>
</template>
<script src="./controller/index.js"/>
